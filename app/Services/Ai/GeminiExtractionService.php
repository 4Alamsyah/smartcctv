<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Wraps the Gemini "generateContent" endpoint in JSON-mode to turn a citizen's
 * free-text CRM complaint into the structured schema `crm_reports` expects.
 */
class GeminiExtractionService
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
     * @return array{category: string, severity: string, summary: string,
     *               confidence: float, coordinates: array{lat: float, lng: float}|null,
     *               raw: array<string, mixed>}
     */
    public function extractFromComplaint(string $rawText, ?string $addressHint = null): array
    {
        $schema = [
            'type' => 'OBJECT',
            'properties' => [
                'category' => [
                    'type' => 'STRING',
                    'enum' => [
                        'illegal_parking', 'busway_lane_intrusion', 'traffic_congestion',
                        'signage_damage', 'road_hazard', 'other',
                    ],
                ],
                'severity' => ['type' => 'STRING', 'enum' => ['low', 'medium', 'high', 'critical']],
                'summary' => ['type' => 'STRING', 'description' => 'One sentence, max 25 words, Bahasa Indonesia'],
                'confidence' => ['type' => 'NUMBER', 'description' => '0-1 extraction confidence'],
                'has_coordinates' => ['type' => 'BOOLEAN'],
                'latitude' => ['type' => 'NUMBER', 'nullable' => true],
                'longitude' => ['type' => 'NUMBER', 'nullable' => true],
            ],
            'required' => ['category', 'severity', 'summary', 'confidence', 'has_coordinates'],
        ];

        $prompt = <<<PROMPT
            You are a traffic enforcement triage assistant for DISHUB DKI Jakarta.
            Extract structured data from the citizen complaint below. If the text
            mentions a specific Jakarta location and you are confident of its
            approximate coordinates, set has_coordinates=true and fill
            latitude/longitude (WGS84). Otherwise set has_coordinates=false and
            leave latitude/longitude null. Never invent a plate number or address.

            Address hint (may be empty): {$addressHint}

            Complaint text:
            """
            {$rawText}
            """
            PROMPT;

        $response = Http::timeout(15)
            ->retry(2, 300, throw: false)
            ->post("{$this->baseUrl}/v1beta/models/{$this->model}:generateContent?key={$this->apiKey}", [
                'contents' => [
                    ['role' => 'user', 'parts' => [['text' => $prompt]]],
                ],
                'generationConfig' => [
                    'responseMimeType' => 'application/json',
                    'responseSchema' => $schema,
                    'temperature' => 0.2,
                ],
            ]);

        if ($response->failed()) {
            throw new RuntimeException("Gemini extraction request failed: {$response->status()} {$response->body()}");
        }

        $json = $response->json();
        $text = data_get($json, 'candidates.0.content.parts.0.text');

        if (! $text) {
            throw new RuntimeException('Gemini extraction returned no candidate text: '.json_encode($json));
        }

        $structured = json_decode($text, true, flags: JSON_THROW_ON_ERROR);

        return [
            'category' => $structured['category'],
            'severity' => $structured['severity'],
            'summary' => $structured['summary'],
            'confidence' => (float) $structured['confidence'],
            'coordinates' => ($structured['has_coordinates'] ?? false)
                ? ['lat' => (float) $structured['latitude'], 'lng' => (float) $structured['longitude']]
                : null,
            'raw' => $json,
        ];
    }
}
