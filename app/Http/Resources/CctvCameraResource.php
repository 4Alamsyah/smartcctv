<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\CctvCamera
 */
class CctvCameraResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'rtsp_url' => $this->rtsp_url,
            'zone_type' => $this->zone_type,
            'lat' => $this->location_lat !== null ? (float) $this->location_lat : null,
            'lng' => $this->location_lng !== null ? (float) $this->location_lng : null,
            // Drop the trailing vertex GeoJSON repeats to close the ring --
            // the map picker works with the distinct points a user drew.
            'lane_geofence' => $this->lane_geofence_geojson
                ? array_slice(json_decode($this->lane_geofence_geojson, true)['coordinates'][0], 0, -1)
                : null,
            'stationary_threshold_seconds' => $this->stationary_threshold_seconds,
            'is_active' => $this->is_active,
            'last_heartbeat_at' => $this->last_heartbeat_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
