<?php

namespace App\Models;

use App\Concerns\HasSpatialColumns;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class CctvCamera extends Model
{
    use HasFactory, HasSpatialColumns;

    protected $fillable = [
        'code', 'name', 'rtsp_url', 'zone_type',
        'stationary_threshold_seconds', 'is_active', 'last_heartbeat_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'last_heartbeat_at' => 'datetime',
            'rtsp_url' => 'encrypted',
        ];
    }

    public function violations(): HasMany
    {
        return $this->hasMany(TrafficViolation::class, 'camera_id');
    }

    /**
     * Set the `location` geometry column from decimal lng/lat without going
     * through Eloquent's plain attribute assignment (PostGIS points aren't a
     * castable scalar). Bypasses setAttribute() so it isn't mistaken for the
     * `rtsp_url` encrypted cast path; combine with normal fill()/save() for
     * the rest of the model's attributes.
     */
    public function setLocationPoint(float $lng, float $lat): static
    {
        $this->attributes['location'] = DB::raw(static::makePointExpression($lng, $lat));

        return $this;
    }

    /**
     * Set the `lane_geofence` polygon from an ordered list of [lng, lat]
     * vertices (as drawn on the map picker). Pass null/empty to clear it --
     * the edge worker treats a missing geofence as "whole frame counts"
     * rather than erroring, so clearing is a valid, intentional state.
     */
    public function setLaneGeofence(?array $points): static
    {
        $this->attributes['lane_geofence'] = $points ? DB::raw(static::makePolygonExpression($points)) : null;

        return $this;
    }
}
