<script setup lang="ts">
import HeadingSmall from '@/components/HeadingSmall.vue';
import CameraPreviewDialog from '@/components/cctv/CameraPreviewDialog.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import type { CctvCamera } from '@/types/cctv';
import { Head, Link, useForm } from '@inertiajs/vue3';
import CameraForm, { type CameraFormData } from './CameraForm.vue';

const props = defineProps<{
    camera: CctvCamera;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'CCTV Management', href: '/cctv-cameras' },
    { title: props.camera.code, href: `/cctv-cameras/${props.camera.id}/edit` },
];

const form = useForm<CameraFormData>({
    code: props.camera.code,
    name: props.camera.name,
    rtsp_url: props.camera.rtsp_url,
    zone_type: props.camera.zone_type,
    lat: props.camera.lat ?? '',
    lng: props.camera.lng ?? '',
    lane_geofence: props.camera.lane_geofence,
    stationary_threshold_seconds: props.camera.stationary_threshold_seconds,
    is_active: props.camera.is_active,
});

const submit = () => {
    form.put(route('cctv-cameras.update', props.camera.id));
};
</script>

<template>
    <Head :title="`Edit ${camera.code}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-4 rounded-xl p-4">
            <div class="flex items-center justify-between gap-4">
                <HeadingSmall :title="`Edit ${camera.code}`" description="Update camera details and RTSP stream." />
                <CameraPreviewDialog :camera="camera">
                    <Button type="button" variant="outline" size="sm">Preview saved stream</Button>
                </CameraPreviewDialog>
            </div>

            <form class="max-w-xl space-y-6" @submit.prevent="submit">
                <CameraForm :form="form" />

                <div class="flex items-center gap-4">
                    <Button :disabled="form.processing">Save changes</Button>
                    <Link :href="route('cctv-cameras.index')" class="text-sm text-muted-foreground hover:underline">Cancel</Link>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
