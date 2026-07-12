<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTrafficViolationRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Actual authorization is the `auth:sanctum` + `ability:edge:ingest`
        // middleware pair on the route; this is a resource-shape guard only.
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'event_uuid' => ['required', 'uuid', Rule::unique('traffic_violations', 'event_uuid')],
            'camera_code' => ['required', 'string', Rule::exists('cctv_cameras', 'code')],

            'violation_type' => ['required', Rule::in(['illegal_parking', 'busway_lane_intrusion'])],

            'plate_number' => ['nullable', 'string', 'max:20'],
            'plate_confidence' => ['nullable', 'integer', 'min:0', 'max:100'],
            'plate_source' => ['required', Rule::in(['edge_ocr', 'groq_fallback', 'manual_review'])],

            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],

            'stationary_seconds' => ['required', 'integer', 'min:0'],
            'threshold_seconds' => ['required', 'integer', 'min:0'],

            'detected_at' => ['required', 'date'],

            'frame' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'clip' => ['nullable', 'mimes:mp4,webm', 'max:20480'],

            'meta' => ['nullable', 'array'],
            'meta.bbox' => ['nullable', 'array', 'size:4'],
            'meta.track_id' => ['nullable', 'string'],
            'meta.class_confidence' => ['nullable', 'numeric', 'between:0,1'],
        ];
    }
}
