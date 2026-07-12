<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import GeofenceMapPicker from '@/components/cctv/GeofenceMapPicker.vue';
import type { FormDataConvertible } from '@inertiajs/core';
import type { InertiaForm } from '@inertiajs/vue3';
import { computed } from 'vue';

export interface CameraFormData {
    code: string;
    name: string;
    rtsp_url: string;
    zone_type: 'general' | 'busway_lane';
    lat: number | string;
    lng: number | string;
    lane_geofence: [number, number][] | null;
    stationary_threshold_seconds: number;
    is_active: boolean;
    [key: string]: FormDataConvertible;
}

const props = defineProps<{
    form: InertiaForm<CameraFormData>;
}>();

const mapCenter = computed(() => {
    const lat = Number(props.form.lat);
    const lng = Number(props.form.lng);
    return {
        lat: Number.isFinite(lat) && lat !== 0 ? lat : -6.2088,
        lng: Number.isFinite(lng) && lng !== 0 ? lng : 106.8456,
    };
});
</script>

<template>
    <div class="grid gap-2">
        <Label for="code">Camera code</Label>
        <Input id="code" v-model="form.code" placeholder="CCTV-JKT-SDN-014" autocomplete="off" />
        <InputError :message="form.errors.code" />
    </div>

    <div class="grid gap-2">
        <Label for="name">Name</Label>
        <Input id="name" v-model="form.name" placeholder="Sudirman - Bundaran HI" autocomplete="off" />
        <InputError :message="form.errors.name" />
    </div>

    <div class="grid gap-2">
        <Label for="rtsp_url">Stream URL (RTSP or HLS)</Label>
        <Input id="rtsp_url" v-model="form.rtsp_url" placeholder="rtsp://user:pass@camera-ip:554/stream1 or https://host/stream.m3u8" autocomplete="off" />
        <InputError :message="form.errors.rtsp_url" />
    </div>

    <div class="grid gap-2">
        <Label for="zone_type">Zone type</Label>
        <select
            id="zone_type"
            v-model="form.zone_type"
            class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
        >
            <option value="general">General</option>
            <option value="busway_lane">Busway lane</option>
        </select>
        <InputError :message="form.errors.zone_type" />
    </div>

    <div class="grid grid-cols-2 gap-4">
        <div class="grid gap-2">
            <Label for="lat">Latitude</Label>
            <Input id="lat" v-model="form.lat" type="text" inputmode="decimal" placeholder="-6.195000" />
            <InputError :message="form.errors.lat" />
        </div>
        <div class="grid gap-2">
            <Label for="lng">Longitude</Label>
            <Input id="lng" v-model="form.lng" type="text" inputmode="decimal" placeholder="106.823000" />
            <InputError :message="form.errors.lng" />
        </div>
    </div>

    <div class="grid gap-2">
        <Label>Lane / no-parking geofence (optional)</Label>
        <GeofenceMapPicker v-model="form.lane_geofence" :center="mapCenter" />
        <InputError :message="form.errors.lane_geofence" />
    </div>

    <div class="grid gap-2">
        <Label for="stationary_threshold_seconds">Stationary threshold (seconds)</Label>
        <Input id="stationary_threshold_seconds" v-model="form.stationary_threshold_seconds" type="number" min="1" max="3600" />
        <InputError :message="form.errors.stationary_threshold_seconds" />
    </div>

    <div class="flex items-center gap-2">
        <Checkbox id="is_active" v-model:checked="form.is_active" />
        <Label for="is_active">Active</Label>
    </div>
</template>
