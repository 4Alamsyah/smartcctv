<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\PatrolScheduleResource;
use App\Models\PatrolSchedule;
use App\Services\Ai\GeminiReportService;
use App\Services\Spatial\HotspotClusterService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class PatrolReportController extends Controller
{
    public function __construct(
        private readonly HotspotClusterService $clusters,
        private readonly GeminiReportService $gemini,
    ) {
    }

    /**
     * GET /api/v1/patrol-schedules/today
     */
    public function today(): AnonymousResourceCollection
    {
        $schedules = PatrolSchedule::query()
            ->selectWithCoordinates('hotspot_location')
            ->whereDate('patrol_date', now()->toDateString())
            ->orderBy('priority')
            ->get();

        return PatrolScheduleResource::collection($schedules);
    }

    /**
     * Administrator-triggered: spatially join violations + CRM hotspots,
     * feed the metrics to Gemini, and persist the proposed dispatch schedule.
     *
     * POST /api/v1/patrol-schedules/generate
     */
    public function generate(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'patrol_date' => ['nullable', 'date'],
            'lookback_hours' => ['nullable', 'integer', 'min:1', 'max:168'],
        ]);

        $patrolDate = $validated['patrol_date'] ?? now()->addDay()->toDateString();
        $hotspots = $this->clusters->clusterRecentActivity(
            lookbackHours: $validated['lookback_hours'] ?? 24,
        );

        if (empty($hotspots)) {
            return PatrolScheduleResource::collection(collect());
        }

        $plan = $this->gemini->generateDispatchPlan($hotspots, $patrolDate);

        $ids = DB::transaction(function () use ($plan, $hotspots, $patrolDate, $request) {
            $ids = [];

            foreach ($plan['schedules'] as $item) {
                $hotspot = $hotspots[$item['hotspot_index']] ?? null;
                if (! $hotspot) {
                    continue;
                }

                $ids[] = DB::selectOne(
                    <<<'SQL'
                        INSERT INTO patrol_schedules
                            (patrol_date, shift, hotspot_location, hotspot_label, priority,
                             linked_violation_count, linked_crm_count, ai_rationale, status,
                             generated_by, created_at, updated_at)
                        VALUES
                            (?, ?, ST_SetSRID(ST_MakePoint(?, ?), 4326), ?, ?, ?, ?, ?, 'scheduled', ?, now(), now())
                        RETURNING id
                        SQL,
                    [
                        $patrolDate,
                        $item['shift'],
                        $hotspot['lng'],
                        $hotspot['lat'],
                        $item['hotspot_label'],
                        $item['priority'],
                        $hotspot['violation_count'],
                        $hotspot['crm_count'],
                        $item['rationale'],
                        $request->user()?->id,
                    ]
                )->id;
            }

            return $ids;
        });

        return PatrolScheduleResource::collection(
            PatrolSchedule::query()
                ->selectWithCoordinates('hotspot_location')
                ->whereIn('id', $ids)
                ->orderBy('priority')
                ->get()
        )->additional(['executive_summary' => $plan['executive_summary']]);
    }
}
