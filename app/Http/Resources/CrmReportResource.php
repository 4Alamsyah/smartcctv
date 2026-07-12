<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\CrmReport
 */
class CrmReportResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ticket_number' => $this->ticket_number,
            'reporter_name' => $this->reporter_name,
            'raw_text' => $this->raw_text,
            'category' => $this->category,
            'severity' => $this->severity,
            'ai_summary' => $this->ai_summary,
            'ai_confidence' => $this->ai_confidence,
            'coordinates' => $this->location_lng !== null ? [
                'lng' => (float) $this->location_lng,
                'lat' => (float) $this->location_lat,
            ] : null,
            'address_text' => $this->address_text,
            'status' => $this->status,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
