<?php

namespace App\Models;

use App\Concerns\HasSpatialColumns;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CrmReport extends Model
{
    use HasFactory, HasSpatialColumns;

    protected $fillable = [
        'ticket_number', 'reporter_name', 'reporter_contact', 'raw_text',
        'category', 'severity', 'ai_summary', 'ai_confidence', 'ai_raw_response',
        'address_text', 'status', 'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'ai_raw_response' => 'array',
            'ai_confidence' => 'float',
            'processed_at' => 'datetime',
        ];
    }
}
