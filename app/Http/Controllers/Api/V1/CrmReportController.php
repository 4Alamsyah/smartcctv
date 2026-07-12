<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCrmReportRequest;
use App\Http\Resources\CrmReportResource;
use App\Models\CrmReport;
use App\Services\Ai\GeminiExtractionService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use RuntimeException;

class CrmReportController extends Controller
{
    public function __construct(private readonly GeminiExtractionService $gemini)
    {
    }

    /**
     * Citizens submit unstructured complaint text; Gemini extracts the
     * structured category/severity/coordinates schema before we persist it.
     *
     * POST /api/v1/crm-reports
     */
    public function store(StoreCrmReportRequest $request): CrmReportResource
    {
        $data = $request->validated();
        $ticketNumber = 'CRM-'.now()->format('Ymd').'-'.strtoupper(Str::random(6));

        try {
            $extraction = $this->gemini->extractFromComplaint($data['raw_text'], $data['address_text'] ?? null);
        } catch (RuntimeException $e) {
            // Gemini being unavailable must not block complaint intake: persist
            // as "received" and let a background retry job classify it later.
            Log::warning('Gemini CRM extraction failed, storing unclassified', ['error' => $e->getMessage()]);
            $extraction = null;
        }

        $locationSql = 'NULL';
        $locationBindings = [];
        if ($extraction && $extraction['coordinates']) {
            $locationSql = 'ST_SetSRID(ST_MakePoint(?, ?), 4326)';
            $locationBindings = [$extraction['coordinates']['lng'], $extraction['coordinates']['lat']];
        }

        $id = DB::selectOne(
            "INSERT INTO crm_reports
                (ticket_number, reporter_name, reporter_contact, raw_text, category, severity,
                 ai_summary, ai_confidence, ai_raw_response, location, address_text, status,
                 processed_at, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, {$locationSql}, ?, ?, ?, now(), now())
             RETURNING id",
            [
                $ticketNumber,
                $data['reporter_name'] ?? null,
                $data['reporter_contact'] ?? null,
                $data['raw_text'],
                $extraction['category'] ?? null,
                $extraction['severity'] ?? null,
                $extraction['summary'] ?? null,
                $extraction['confidence'] ?? null,
                $extraction ? json_encode($extraction['raw']) : null,
                ...$locationBindings,
                $data['address_text'] ?? null,
                $extraction ? 'processed' : 'received',
                $extraction ? now() : null,
            ]
        )->id;

        return new CrmReportResource(
            CrmReport::query()->selectWithCoordinates()->findOrFail($id)
        );
    }

    /**
     * GET /api/v1/crm-reports?category=&severity=&status=
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'category' => ['nullable', 'string'],
            'severity' => ['nullable', Rule::in(['low', 'medium', 'high', 'critical'])],
            'status' => ['nullable', Rule::in(['received', 'processed', 'triaged', 'resolved', 'rejected'])],
        ]);

        $query = CrmReport::query()
            ->selectWithCoordinates()
            ->when($request->category, fn ($q, $v) => $q->where('category', $v))
            ->when($request->severity, fn ($q, $v) => $q->where('severity', $v))
            ->when($request->status, fn ($q, $v) => $q->where('status', $v))
            ->latest();

        return CrmReportResource::collection($query->paginate(50));
    }
}
