<?php

namespace App\Http\Controllers\Api\V1;

use App\Events\ViolationDetected;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTrafficViolationRequest;
use App\Http\Resources\TrafficViolationResource;
use App\Models\CctvCamera;
use App\Models\TrafficViolation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class TrafficViolationController extends Controller
{
    /**
     * Ingest a violation event pushed by the Python edge worker.
     *
     * POST /api/v1/violations (multipart/form-data)
     */
    public function store(StoreTrafficViolationRequest $request): JsonResponse
    {
        $data = $request->validated();

        $camera = CctvCamera::where('code', $data['camera_code'])->firstOrFail();

        $framePath = $request->file('frame')->store("violations/{$camera->code}/".now()->format('Y/m/d'), 'public');
        $clipPath = $request->hasFile('clip')
            ? $request->file('clip')->store("violations/{$camera->code}/".now()->format('Y/m/d'), 'public')
            : null;

        // Raw PostGIS insert: ST_SetSRID/ST_MakePoint builds a WGS84 geometry(Point,4326)
        // from the edge worker's decimal lat/lng. All values bound via `?` placeholders.
        $id = DB::selectOne(
            <<<'SQL'
                INSERT INTO traffic_violations
                    (event_uuid, camera_id, violation_type, plate_number, plate_confidence,
                     plate_source, location, stationary_seconds, threshold_seconds,
                     frame_path, clip_path, detected_at, status, meta, created_at, updated_at)
                VALUES
                    (?, ?, ?, ?, ?, ?, ST_SetSRID(ST_MakePoint(?, ?), 4326), ?, ?, ?, ?, ?, ?, ?, now(), now())
                RETURNING id
                SQL,
            [
                $data['event_uuid'],
                $camera->id,
                $data['violation_type'],
                $data['plate_number'] ?? null,
                $data['plate_confidence'] ?? null,
                $data['plate_source'],
                (float) $data['lng'],
                (float) $data['lat'],
                $data['stationary_seconds'],
                $data['threshold_seconds'],
                $framePath,
                $clipPath,
                $data['detected_at'],
                'pending_review',
                isset($data['meta']) ? json_encode($data['meta']) : null,
            ]
        )->id;

        $camera->update(['last_heartbeat_at' => now()]);

        $violation = TrafficViolation::query()
            ->selectWithCoordinates()
            ->with('camera')
            ->findOrFail($id);

        broadcast(new ViolationDetected($violation));

        return (new TrafficViolationResource($violation))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * GeoJSON-ready listing for the dashboard map/heatmap layer.
     *
     * GET /api/v1/violations?status=pending_review&bbox=lng1,lat1,lng2,lat2
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'status' => ['nullable', Rule::in(['pending_review', 'confirmed', 'dismissed', 'dispatched'])],
            'violation_type' => ['nullable', Rule::in(['illegal_parking', 'busway_lane_intrusion'])],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'bbox' => ['nullable', 'string'],
        ]);

        $query = TrafficViolation::query()
            ->selectWithCoordinates()
            ->with('camera')
            ->when($request->status, fn ($q, $status) => $q->where('status', $status))
            ->when($request->violation_type, fn ($q, $type) => $q->where('violation_type', $type))
            ->when($request->from, fn ($q, $from) => $q->where('detected_at', '>=', $from))
            ->when($request->to, fn ($q, $to) => $q->where('detected_at', '<=', $to))
            ->when($request->bbox, function ($q, $bbox) {
                [$minLng, $minLat, $maxLng, $maxLat] = array_map('floatval', explode(',', $bbox));

                return $q->whereRaw(
                    'location && ST_MakeEnvelope(?, ?, ?, ?, 4326)',
                    [$minLng, $minLat, $maxLng, $maxLat]
                );
            })
            ->latest('detected_at');

        return TrafficViolationResource::collection($query->paginate(50));
    }

    public function show(TrafficViolation $violation): TrafficViolationResource
    {
        return new TrafficViolationResource(
            TrafficViolation::query()->selectWithCoordinates()->with('camera')->findOrFail($violation->id)
        );
    }

    public function updateStatus(Request $request, TrafficViolation $violation): TrafficViolationResource
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['confirmed', 'dismissed', 'dispatched'])],
        ]);

        $violation->update([
            'status' => $data['status'],
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        return new TrafficViolationResource(
            $violation->newQuery()->selectWithCoordinates()->findOrFail($violation->id)
        );
    }
}
