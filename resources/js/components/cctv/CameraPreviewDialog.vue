<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Dialog, DialogClose, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import Hls from 'hls.js';
import { nextTick, ref } from 'vue';

const props = defineProps<{
    camera: { id: number; code: string; name: string; rtsp_url: string };
}>();

const isHttpStream = /^https?:\/\//i.test(props.camera.rtsp_url);

const videoRef = ref<HTMLVideoElement | null>(null);
let hls: Hls | null = null;

type ConnectionState = 'idle' | 'checking' | 'reachable' | 'unreachable';
const connectionState = ref<ConnectionState>('idle');
const connectionMessage = ref('');

type VideoState = 'idle' | 'loading' | 'playing' | 'error';
const videoState = ref<VideoState>('idle');

async function checkConnection() {
    connectionState.value = 'checking';
    try {
        const response = await fetch(route('cctv-cameras.check-connection', props.camera.id), {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        });
        const data: { reachable: boolean; message: string } = await response.json();
        connectionState.value = data.reachable ? 'reachable' : 'unreachable';
        connectionMessage.value = data.message;
    } catch {
        connectionState.value = 'unreachable';
        connectionMessage.value = 'Request to the server failed.';
    }
}

function startVideoPreview() {
    const video = videoRef.value;
    if (!video) return;

    videoState.value = 'loading';

    // Routed through our own origin (see CctvCameraController::proxyHlsStream)
    // instead of camera.rtsp_url directly: most camera/CDN servers don't send
    // Access-Control-Allow-Origin, so hls.js's cross-origin XHR gets blocked
    // by the browser even though the stream itself is reachable.
    const proxiedUrl = route('cctv-cameras.hls-proxy', props.camera.id);

    if (Hls.isSupported()) {
        hls = new Hls();
        hls.loadSource(proxiedUrl);
        hls.attachMedia(video);
        hls.on(Hls.Events.MANIFEST_PARSED, () => {
            videoState.value = 'playing';
            void video.play();
        });
        hls.on(Hls.Events.ERROR, (_event, data) => {
            if (data.fatal) videoState.value = 'error';
        });
    } else if (video.canPlayType('application/vnd.apple.mpegurl')) {
        // Safari plays HLS natively, no hls.js needed.
        video.src = proxiedUrl;
        video.addEventListener('loadedmetadata', () => {
            videoState.value = 'playing';
            void video.play();
        });
        video.addEventListener('error', () => (videoState.value = 'error'));
    } else {
        videoState.value = 'error';
    }
}

function stopVideoPreview() {
    hls?.destroy();
    hls = null;
    if (videoRef.value) {
        videoRef.value.removeAttribute('src');
        videoRef.value.load();
    }
    videoState.value = 'idle';
}

async function onOpenChange(open: boolean) {
    if (open) {
        connectionState.value = 'idle';
        connectionMessage.value = '';
        void checkConnection();

        if (isHttpStream) {
            await nextTick();
            startVideoPreview();
        }
    } else {
        stopVideoPreview();
    }
}
</script>

<template>
    <Dialog @update:open="onOpenChange">
        <DialogTrigger as-child>
            <slot>
                <Button variant="outline" size="sm">Preview</Button>
            </slot>
        </DialogTrigger>
        <DialogContent class="max-w-2xl">
            <DialogHeader>
                <DialogTitle>{{ camera.name }} <span class="font-mono text-xs text-muted-foreground">({{ camera.code }})</span></DialogTitle>
                <DialogDescription>Live connectivity check for this camera's stream.</DialogDescription>
            </DialogHeader>

            <div class="flex items-center gap-2 text-sm">
                <span
                    :class="[
                        'h-2 w-2 shrink-0 rounded-full',
                        connectionState === 'reachable' && 'bg-green-500',
                        connectionState === 'unreachable' && 'bg-red-500',
                        connectionState === 'checking' && 'animate-pulse bg-amber-500',
                        connectionState === 'idle' && 'bg-muted-foreground/40',
                    ]"
                />
                <span v-if="connectionState === 'checking'">Checking connection&hellip;</span>
                <span v-else-if="connectionState !== 'idle'">{{ connectionMessage }}</span>
            </div>

            <div v-if="isHttpStream" class="aspect-video overflow-hidden rounded-lg bg-black">
                <video ref="videoRef" class="h-full w-full" controls muted autoplay playsinline />
                <p v-if="videoState === 'error'" class="p-3 text-sm text-destructive">
                    Stream failed to load in the browser. Check the connectivity status above and the URL.
                </p>
            </div>
            <div v-else class="rounded-lg border border-dashed p-4 text-sm text-muted-foreground">
                RTSP streams can't be played directly in a browser. The status above only confirms the camera host accepts a TCP connection &mdash; to
                actually view the feed, open the URL in VLC (Media &rarr; Open Network Stream).
            </div>

            <DialogFooter>
                <DialogClose as-child>
                    <Button variant="secondary">Close</Button>
                </DialogClose>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
