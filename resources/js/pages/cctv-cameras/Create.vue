<script setup lang="ts">
import HeadingSmall from '@/components/HeadingSmall.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/vue3';
import CameraForm, { type CameraFormData } from './CameraForm.vue';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'CCTV Management', href: '/cctv-cameras' },
    { title: 'Add camera', href: '/cctv-cameras/create' },
];

const form = useForm<CameraFormData>({
    code: '',
    name: '',
    rtsp_url: '',
    zone_type: 'general',
    lat: '',
    lng: '',
    lane_geofence: null,
    stationary_threshold_seconds: 180,
    is_active: true,
});

const submit = () => {
    form.post(route('cctv-cameras.store'));
};
</script>

<template>
    <Head title="Add CCTV camera" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-4 rounded-xl p-4">
            <HeadingSmall title="Add CCTV camera" description="Register a new camera and its RTSP stream." />

            <form class="max-w-xl space-y-6" @submit.prevent="submit">
                <CameraForm :form="form" />

                <div class="flex items-center gap-4">
                    <Button :disabled="form.processing">Save camera</Button>
                    <Link :href="route('cctv-cameras.index')" class="text-sm text-muted-foreground hover:underline">Cancel</Link>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
