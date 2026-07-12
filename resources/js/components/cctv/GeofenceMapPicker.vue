<script setup lang="ts">
/**
 * Click-to-draw polygon editor for a camera's `lane_geofence`. Vertices are
 * stored/emitted as [lng, lat] pairs (matching the DB/GeoJSON convention
 * used everywhere else in this app), even though Leaflet itself works in
 * lat/lng order internally.
 */
import { Button } from '@/components/ui/button';
import * as L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';

const props = defineProps<{
    modelValue: [number, number][] | null;
    center: { lat: number; lng: number };
}>();

const emit = defineEmits<{
    'update:modelValue': [value: [number, number][] | null];
}>();

const mapContainer = ref<HTMLDivElement | null>(null);
let map: L.Map | null = null;
let polygon: L.Polygon | L.Polyline | null = null;
let vertexMarkers: L.CircleMarker[] = [];
let centerMarker: L.Marker | null = null;

const points = ref<[number, number][]>(props.modelValue ? [...props.modelValue] : []);

function redraw() {
    if (!map) return;

    polygon?.remove();
    vertexMarkers.forEach((m) => m.remove());
    vertexMarkers = [];

    const latLngs = points.value.map(([lng, lat]) => [lat, lng] as [number, number]);

    if (latLngs.length >= 3) {
        polygon = L.polygon(latLngs, { color: '#dc2626', fillOpacity: 0.15 }).addTo(map);
    } else if (latLngs.length >= 1) {
        polygon = L.polyline(latLngs, { color: '#dc2626' }).addTo(map);
    } else {
        polygon = null;
    }

    latLngs.forEach((latLng) => {
        vertexMarkers.push(L.circleMarker(latLng, { radius: 5, color: '#dc2626', fillColor: '#fff', fillOpacity: 1 }).addTo(map!));
    });
}

function addPoint(e: L.LeafletMouseEvent) {
    points.value.push([e.latlng.lng, e.latlng.lat]);
    redraw();
    emitValue();
}

function undoLast() {
    points.value.pop();
    redraw();
    emitValue();
}

function clearAll() {
    points.value = [];
    redraw();
    emitValue();
}

function emitValue() {
    emit('update:modelValue', points.value.length >= 3 ? points.value : null);
}

watch(
    () => props.center,
    (center) => {
        map?.setView([center.lat, center.lng]);
        centerMarker?.setLatLng([center.lat, center.lng]);
    },
);

onMounted(() => {
    map = L.map(mapContainer.value!, { zoomControl: true }).setView([props.center.lat, props.center.lng], 16);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors',
        maxZoom: 19,
    }).addTo(map);

    centerMarker = L.marker([props.center.lat, props.center.lng]).addTo(map);
    map.on('click', addPoint);

    redraw();
});

onBeforeUnmount(() => {
    map?.remove();
});
</script>

<template>
    <div class="space-y-2">
        <div class="flex items-center justify-between">
            <p class="text-sm text-muted-foreground">Click the map to draw the geofence polygon ({{ points.length }} point(s), min 3).</p>
            <div class="flex gap-2">
                <Button type="button" variant="secondary" size="sm" :disabled="points.length === 0" @click="undoLast">Undo point</Button>
                <Button type="button" variant="secondary" size="sm" :disabled="points.length === 0" @click="clearAll">Clear</Button>
            </div>
        </div>
        <div ref="mapContainer" class="h-72 w-full overflow-hidden rounded-lg border border-sidebar-border/70 dark:border-sidebar-border" />
    </div>
</template>
