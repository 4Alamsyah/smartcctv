<script setup lang="ts">
import HeadingSmall from '@/components/HeadingSmall.vue';
import CameraPreviewDialog from '@/components/cctv/CameraPreviewDialog.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogClose, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import type { Paginated, CctvCamera } from '@/types/cctv';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps<{
    cameras: Paginated<CctvCamera>;
    filters: { search?: string };
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'CCTV Management', href: '/cctv-cameras' }];

const search = ref(props.filters.search ?? '');

const runSearch = () => {
    router.get(route('cctv-cameras.index'), { search: search.value || undefined }, { preserveState: true, replace: true });
};

const destroy = (camera: CctvCamera) => {
    router.delete(route('cctv-cameras.destroy', camera.id), { preserveScroll: true });
};
</script>

<template>
    <Head title="CCTV Management" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-4 rounded-xl p-4">
            <div class="flex items-center justify-between gap-4">
                <HeadingSmall title="CCTV Management" description="Manage registered cameras and their RTSP streams." />
                <Button as-child>
                    <Link :href="route('cctv-cameras.create')">Add camera</Link>
                </Button>
            </div>

            <form class="flex max-w-sm gap-2" @submit.prevent="runSearch">
                <Input v-model="search" placeholder="Search by code or name" />
                <Button type="submit" variant="secondary">Search</Button>
            </form>

            <div class="overflow-x-auto rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-sidebar-border/70 bg-muted/50 dark:border-sidebar-border">
                        <tr>
                            <th class="px-4 py-3 font-medium">Code</th>
                            <th class="px-4 py-3 font-medium">Name</th>
                            <th class="px-4 py-3 font-medium">Zone</th>
                            <th class="px-4 py-3 font-medium">Coordinates</th>
                            <th class="px-4 py-3 font-medium">Threshold</th>
                            <th class="px-4 py-3 font-medium">Status</th>
                            <th class="px-4 py-3 font-medium">Last heartbeat</th>
                            <th class="px-4 py-3 font-medium text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="camera in cameras.data" :key="camera.id" class="border-b border-sidebar-border/70 last:border-0 dark:border-sidebar-border">
                            <td class="px-4 py-3 font-mono text-xs">{{ camera.code }}</td>
                            <td class="px-4 py-3">{{ camera.name }}</td>
                            <td class="px-4 py-3">{{ camera.zone_type === 'busway_lane' ? 'Busway lane' : 'General' }}</td>
                            <td class="px-4 py-3 text-xs text-muted-foreground">
                                <span v-if="camera.lat !== null && camera.lng !== null">{{ camera.lat.toFixed(5) }}, {{ camera.lng.toFixed(5) }}</span>
                                <span v-else>&mdash;</span>
                            </td>
                            <td class="px-4 py-3">{{ camera.stationary_threshold_seconds }}s</td>
                            <td class="px-4 py-3">
                                <span
                                    :class="[
                                        'rounded-full px-2 py-0.5 text-xs',
                                        camera.is_active ? 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300' : 'bg-muted text-muted-foreground',
                                    ]"
                                >
                                    {{ camera.is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-xs text-muted-foreground">
                                {{ camera.last_heartbeat_at ? new Date(camera.last_heartbeat_at).toLocaleString() : 'Never' }}
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex justify-end gap-2">
                                    <CameraPreviewDialog :camera="camera">
                                        <button type="button" class="text-sm text-primary hover:underline">Preview</button>
                                    </CameraPreviewDialog>

                                    <Link :href="route('cctv-cameras.edit', camera.id)" class="text-sm text-primary hover:underline">Edit</Link>

                                    <Dialog>
                                        <DialogTrigger as-child>
                                            <button type="button" class="text-sm text-destructive hover:underline">Delete</button>
                                        </DialogTrigger>
                                        <DialogContent>
                                            <DialogHeader class="space-y-3">
                                                <DialogTitle>Delete camera {{ camera.code }}?</DialogTitle>
                                                <DialogDescription>
                                                    This will permanently remove the camera and stop the edge worker from reporting under this code. This
                                                    cannot be undone.
                                                </DialogDescription>
                                            </DialogHeader>
                                            <DialogFooter>
                                                <DialogClose as-child>
                                                    <Button variant="secondary">Cancel</Button>
                                                </DialogClose>
                                                <DialogClose as-child>
                                                    <Button variant="destructive" @click="destroy(camera)">Delete</Button>
                                                </DialogClose>
                                            </DialogFooter>
                                        </DialogContent>
                                    </Dialog>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="cameras.data.length === 0">
                            <td colspan="8" class="px-4 py-8 text-center text-muted-foreground">No cameras registered yet.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="cameras.meta.links.length > 3" class="flex flex-wrap gap-1">
                <template v-for="(link, index) in cameras.meta.links" :key="index">
                    <Link
                        v-if="link.url"
                        :href="link.url"
                        preserve-scroll
                        preserve-state
                        :class="[
                            'rounded-md px-3 py-1 text-sm',
                            link.active ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:bg-muted',
                        ]"
                        v-html="link.label"
                    />
                    <span v-else class="rounded-md px-3 py-1 text-sm text-muted-foreground/50" v-html="link.label" />
                </template>
            </div>
        </div>
    </AppLayout>
</template>
