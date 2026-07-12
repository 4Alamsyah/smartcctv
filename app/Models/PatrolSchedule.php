<?php

namespace App\Models;

use App\Concerns\HasSpatialColumns;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PatrolSchedule extends Model
{
    use HasFactory, HasSpatialColumns;

    protected $fillable = [
        'patrol_date', 'officer_name', 'unit_code', 'shift', 'hotspot_label',
        'priority', 'linked_violation_count', 'linked_crm_count',
        'ai_rationale', 'status', 'generated_by',
    ];

    protected function casts(): array
    {
        return [
            'patrol_date' => 'date',
            'priority' => 'integer',
        ];
    }

    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }
}
