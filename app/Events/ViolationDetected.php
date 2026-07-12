<?php

namespace App\Events;

use App\Models\TrafficViolation;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Queue\SerializesModels;

/**
 * Fired the moment a violation payload from the Python edge worker is persisted.
 * Queued (not ShouldBroadcastNow) so an ingest spike never blocks the HTTP
 * response back to the edge worker on Reverb availability.
 */
class ViolationDetected implements ShouldBroadcast
{
    use InteractsWithSockets, SerializesModels;

    public function __construct(public TrafficViolation $violation)
    {
    }

    /** @return array<int, Channel> */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('dispatch-center'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'violation.detected';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->violation->id,
            'event_uuid' => $this->violation->event_uuid,
            'violation_type' => $this->violation->violation_type,
            'plate_number' => $this->violation->plate_number,
            'plate_confidence' => $this->violation->plate_confidence,
            'camera_id' => $this->violation->camera_id,
            'lng' => $this->violation->location_lng,
            'lat' => $this->violation->location_lat,
            'stationary_seconds' => $this->violation->stationary_seconds,
            'frame_url' => $this->violation->frame_path
                ? \Illuminate\Support\Facades\Storage::disk('public')->url($this->violation->frame_path)
                : null,
            'detected_at' => $this->violation->detected_at?->toIso8601String(),
            'status' => $this->violation->status,
        ];
    }
}
