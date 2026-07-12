<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * @mixin \App\Models\TrafficViolation
 */
class TrafficViolationResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'event_uuid' => $this->event_uuid,
            'camera' => [
                'id' => $this->camera?->id,
                'code' => $this->camera?->code,
                'name' => $this->camera?->name,
            ],
            'violation_type' => $this->violation_type,
            'plate_number' => $this->plate_number,
            'plate_confidence' => $this->plate_confidence,
            'plate_source' => $this->plate_source,
            'coordinates' => [
                'lng' => (float) $this->location_lng,
                'lat' => (float) $this->location_lat,
            ],
            'geojson' => $this->location_geojson ? json_decode($this->location_geojson) : null,
            'stationary_seconds' => $this->stationary_seconds,
            'threshold_seconds' => $this->threshold_seconds,
            'frame_url' => $this->frame_path ? Storage::disk('public')->url($this->frame_path) : null,
            'clip_url' => $this->clip_path ? Storage::disk('public')->url($this->clip_path) : null,
            'status' => $this->status,
            'detected_at' => $this->detected_at?->toIso8601String(),
            'reviewed_at' => $this->reviewed_at?->toIso8601String(),
        ];
    }
}
