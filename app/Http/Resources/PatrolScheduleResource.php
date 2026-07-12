<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\PatrolSchedule
 */
class PatrolScheduleResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'patrol_date' => $this->patrol_date?->toDateString(),
            'shift' => $this->shift,
            'officer_name' => $this->officer_name,
            'unit_code' => $this->unit_code,
            'hotspot_label' => $this->hotspot_label,
            'coordinates' => [
                'lng' => (float) $this->hotspot_location_lng,
                'lat' => (float) $this->hotspot_location_lat,
            ],
            'priority' => $this->priority,
            'linked_violation_count' => $this->linked_violation_count,
            'linked_crm_count' => $this->linked_crm_count,
            'ai_rationale' => $this->ai_rationale,
            'status' => $this->status,
        ];
    }
}
