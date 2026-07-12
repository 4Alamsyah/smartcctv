<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Turns hotspot cluster metrics (from HotspotClusterService) into a
 * human-readable executive summary plus a structured officer dispatch plan.
 */
class GeminiReportService
{
    private string $baseUrl;

    private string $model;

    private string $apiKey;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.gemini.base_url'), '/');
        $this->model = config('services.gemini.model');
        $this->apiKey = config('services.gemini.key');
    }

    /**
     * @param  array<int, array<string, mixed>>  $hotspots  output of HotspotClusterService::clusterRecentActivity()
     * @return array{executive_summary: string, schedules: array<int, array<string, mixed>>}
     */
    public function generateDispatchPlan(array $hotspots, string $patrolDate): array
    {
        $schema = [
            'type' => 'OBJECT',
            'properties' => [
                'executive_summary' => [
                    'type' => 'STRING',
                    'description' => '3-5 sentence Bahasa Indonesia executive summary for the DISHUB duty officer',
                ],
                'schedules' => [
                    'type' => 'ARRAY',
                    'items' => [
                        'type' => 'OBJECT',
                        'properties' => [
                            'hotspot_index' => ['type' => 'INTEGER', 'description' => 'Index into the provided hotspot list'],
                            'hotspot_label' => ['type' => 'STRING'],
                            'shift' => ['type' => 'STRING', 'enum' => ['pagi', 'siang', 'malam']],
                            'priority' => ['type' => 'INTEGER', 'description' => '1 (highest) to 5 (lowest)'],
                            'recommended_unit_count' => ['type' => 'INTEGER'],
                            'rationale' => ['type' => 'STRING', 'description' => 'Max 2 sentences, Bahasa Indonesia'],
                        ],
                        'required' => ['hotspot_index', 'hotspot_label', 'shift', 'priority', 'rationale'],
                    ],
                ],
            ],
            'required' => ['executive_summary', 'schedules'],
        ];

        $hotspotList = collect($hotspots)->values()->map(fn ($h, $i) => sprintf(
            '[%d] lng=%.6f lat=%.6f violations=%d crm_reports=%d dominant_category=%s',
            $i, $h['lng'], $h['lat'], $h['violation_count'], $h['crm_count'], $h['dominant_category'] ?? 'n/a'
        ))->implode("\n");

        $prompt = <<<PROMPT
            You are a strategic dispatch planner for DISHUB DKI Jakarta traffic
            enforcement. Below is a list of spatial hotspot clusters for
            {$patrolDate}, each combining confirmed CCTV violation detections
            and citizen CRM complaints within ~250m of each other.

            Hotspots (index: coordinates, counts):
            {$hotspotList}

            Propose a patrol dispatch schedule: prioritize hotspots with higher
            total activity and busway-lane intrusions over general parking.
            Distribute across pagi/siang/malam shifts realistically -- do not
            put every hotspot in the same shift. Reference hotspot_index values
            exactly as given above.
            PROMPT;

        $response = Http::timeout(30)
            ->retry(2, 500, throw: false)
            ->post("{$this->baseUrl}/v1beta/models/{$this->model}:generateContent?key={$this->apiKey}", [
                'contents' => [
                    ['role' => 'user', 'parts' => [['text' => $prompt]]],
                ],
                'generationConfig' => [
                    'responseMimeType' => 'application/json',
                    'responseSchema' => $schema,
                    'temperature' => 0.4,
                ],
            ]);

        if ($response->failed()) {
            throw new RuntimeException("Gemini dispatch planning request failed: {$response->status()} {$response->body()}");
        }

        $text = data_get($response->json(), 'candidates.0.content.parts.0.text');

        if (! $text) {
            throw new RuntimeException('Gemini dispatch planning returned no candidate text.');
        }

        return json_decode($text, true, flags: JSON_THROW_ON_ERROR);
    }
}
