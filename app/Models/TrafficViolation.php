<?php

namespace App\Models;

use App\Concerns\HasSpatialColumns;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrafficViolation extends Model
{
    use HasFactory, HasSpatialColumns;

    protected $fillable = [
        'event_uuid', 'camera_id', 'violation_type', 'plate_number',
        'plate_confidence', 'plate_source', 'stationary_seconds',
        'threshold_seconds', 'frame_path', 'clip_path', 'detected_at',
        'status', 'reviewed_by', 'reviewed_at', 'meta',
    ];

    protected function casts(): array
    {
        return [
            'detected_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'meta' => 'array',
            'plate_confidence' => 'integer',
            'stationary_seconds' => 'integer',
        ];
    }

    public function camera(): BelongsTo
    {
        return $this->belongsTo(CctvCamera::class, 'camera_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
