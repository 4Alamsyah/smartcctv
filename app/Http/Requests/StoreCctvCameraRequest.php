<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCctvCameraRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:30', Rule::unique('cctv_cameras', 'code')],
            'name' => ['required', 'string', 'max:255'],
            'rtsp_url' => ['required', 'string', 'max:500', 'regex:/^(rtsps?|https?):\/\//i'],
            'zone_type' => ['required', Rule::in(['general', 'busway_lane'])],
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
            'stationary_threshold_seconds' => ['required', 'integer', 'min:1', 'max:3600'],
            'is_active' => ['boolean'],

            'lane_geofence' => ['nullable', 'array', 'min:3'],
            'lane_geofence.*' => ['array', 'size:2'],
            'lane_geofence.*.0' => ['numeric', 'between:-180,180'],
            'lane_geofence.*.1' => ['numeric', 'between:-90,90'],
        ];
    }
}
