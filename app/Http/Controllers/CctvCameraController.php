<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCctvCameraRequest;
use App\Http\Requests\UpdateCctvCameraRequest;
use App\Http\Resources\CctvCameraResource;
use App\Models\CctvCamera;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class CctvCameraController extends Controller
{
    public function index(Request $request): Response
    {
        $cameras = CctvCamera::query()
            ->selectWithCoordinates()
            ->when($request->string('search')->toString(), fn ($q, $search) => $q->where(function ($q) use ($search) {
                $q->where('code', 'ilike', "%{$search}%")->orWhere('name', 'ilike', "%{$search}%");
            }))
            ->orderBy('code')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('cctv-cameras/Index', [
            'cameras' => CctvCameraResource::collection($cameras)->response()->getData(true),
            'filters' => $request->only('search'),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('cctv-cameras/Create');
    }

    public function store(StoreCctvCameraRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $camera = new CctvCamera($data);
        $camera->setLocationPoint((float) $data['lng'], (float) $data['lat']);
        $camera->setLaneGeofence($data['lane_geofence'] ?? null);
        $camera->save();

        return to_route('cctv-cameras.index')->with('success', "Camera {$camera->code} created.");
    }

    public function edit(CctvCamera $cctvCamera): Response
    {
        $camera = CctvCamera::query()
            ->selectWithCoordinates()
            ->selectGeoJson('lane_geofence')
            ->findOrFail($cctvCamera->id);

        return Inertia::render('cctv-cameras/Edit', [
            // JsonResource implements Responsable, which Inertia auto-detects and
            // wraps as {"data": ...} via toResponse(); ->resolve() sidesteps that
            // and hands the plain transformed array straight to the Vue prop.
            'camera' => (new CctvCameraResource($camera))->resolve(),
        ]);
    }

    public function update(UpdateCctvCameraRequest $request, CctvCamera $cctvCamera): RedirectResponse
    {
        $data = $request->validated();

        $cctvCamera->fill($data);
        $cctvCamera->setLocationPoint((float) $data['lng'], (float) $data['lat']);
        $cctvCamera->setLaneGeofence($data['lane_geofence'] ?? null);
        $cctvCamera->save();

        return to_route('cctv-cameras.index')->with('success', "Camera {$cctvCamera->code} updated.");
    }

    public function destroy(CctvCamera $cctvCamera): RedirectResponse
    {
        $code = $cctvCamera->code;
        $cctvCamera->delete();

        return to_route('cctv-cameras.index')->with('success', "Camera {$code} deleted.");
    }

    /**
     * Camera config for the Python edge worker (Sanctum `edge:ingest` token,
     * see routes/api.php). Called once at startup so `rtsp_url`, `zone_type`,
     * and the `lane_geofence` polygon all come from the camera's actual DB
     * record instead of being duplicated/hardcoded in the Python .env.
     */
    public function showConfig(string $code): JsonResponse
    {
        $camera = CctvCamera::query()
            ->selectGeoJson('lane_geofence')
            ->where('code', $code)
            ->firstOrFail();

        $geofence = null;
        if ($camera->lane_geofence_geojson) {
            // GeoJSON Polygon: coordinates[0] is the exterior ring, an array
            // of [lng, lat] pairs (closed -- first point repeats as last).
            $geofence = json_decode($camera->lane_geofence_geojson, true)['coordinates'][0];
        }

        return response()->json([
            'code' => $camera->code,
            'rtsp_url' => $camera->rtsp_url,
            'zone_type' => $camera->zone_type,
            'stationary_threshold_seconds' => $camera->stationary_threshold_seconds,
            'lane_geofence' => $geofence,
        ]);
    }

    /**
     * Server-side reachability probe. RTSP can't be played in a browser at
     * all (no native or JS decoder), so the best a "preview" can offer there
     * is confirming the stream host:port actually accepts connections. HTTP(S)
     * streams (HLS/MJPEG) get the same check for consistency, even though the
     * frontend also attempts real playback for those via hls.js.
     */
    public function checkConnection(CctvCamera $cctvCamera): JsonResponse
    {
        $parts = parse_url($cctvCamera->rtsp_url);
        $scheme = strtolower($parts['scheme'] ?? '');
        $host = $parts['host'] ?? null;

        if (! $host) {
            return response()->json(['reachable' => false, 'message' => 'Could not parse a host from the stream URL.']);
        }

        if (in_array($scheme, ['rtsp', 'rtsps'], true)) {
            $port = $parts['port'] ?? 554;
            $socket = @fsockopen($host, $port, $errno, $errstr, 4);

            if ($socket) {
                fclose($socket);

                return response()->json(['reachable' => true, 'message' => "TCP connection to {$host}:{$port} succeeded."]);
            }

            return response()->json(['reachable' => false, 'message' => $errstr ?: "Could not reach {$host}:{$port}."]);
        }

        if (in_array($scheme, ['http', 'https'], true)) {
            try {
                $response = Http::timeout(5)->connectTimeout(4)->withOptions(['stream' => true])->get($cctvCamera->rtsp_url);

                return response()->json([
                    'reachable' => $response->successful(),
                    'message' => "HTTP {$response->status()}",
                ]);
            } catch (Throwable $e) {
                return response()->json(['reachable' => false, 'message' => 'Connection failed: '.$e->getMessage()]);
            }
        }

        return response()->json(['reachable' => false, 'message' => "Unsupported stream scheme: {$scheme}"]);
    }

    /**
     * Relay an HLS playlist/segment through our own origin so the browser's
     * XHR (via hls.js) is same-origin -- most public camera/CDN servers
     * don't send Access-Control-Allow-Origin, which blocks direct browser
     * fetches even though the resource itself is public.
     *
     * With no `path` query param this fetches the camera's own stream URL
     * (the master or media playlist). Every URI line inside a fetched
     * playlist is rewritten to point back through this same proxy so
     * variant playlists and .ts segments are relayed too.
     */
    public function proxyHlsStream(Request $request, CctvCamera $cctvCamera)
    {
        if (! preg_match('/^https?:\/\//i', $cctvCamera->rtsp_url)) {
            abort(422, 'Only HTTP(S) streams can be proxied.');
        }

        $relativePath = $request->query('path');
        $targetUrl = $relativePath
            ? $this->resolveStreamUrl($cctvCamera->rtsp_url, $relativePath)
            : $cctvCamera->rtsp_url;

        try {
            $upstream = Http::timeout(10)->connectTimeout(5)->withOptions(['stream' => true])->get($targetUrl);
        } catch (Throwable $e) {
            abort(502, 'Could not reach the camera stream: '.$e->getMessage());
        }

        if (! $upstream->successful()) {
            abort(502, "Camera stream returned HTTP {$upstream->status()}.");
        }

        $isPlaylist = str_ends_with(parse_url($targetUrl, PHP_URL_PATH) ?? '', '.m3u8');

        return response(
            $isPlaylist ? $this->rewritePlaylistUris($upstream->body(), $cctvCamera) : $upstream->body(),
            200,
            [
                'Content-Type' => $isPlaylist ? 'application/vnd.apple.mpegurl' : ($upstream->header('Content-Type') ?: 'video/mp2t'),
                'Access-Control-Allow-Origin' => '*',
                'Cache-Control' => 'no-store',
            ]
        );
    }

    /**
     * Resolve a playlist-referenced URI against the camera's base stream
     * URL. Absolute URIs are only allowed when they stay on the camera's
     * own host -- otherwise the `path` query param would turn this proxy
     * into an open relay any authenticated user could point at arbitrary
     * hosts (including internal/private network addresses).
     */
    private function resolveStreamUrl(string $baseUrl, string $relative): string
    {
        $base = parse_url($baseUrl);

        if (preg_match('/^https?:\/\//i', $relative)) {
            $relativeHost = parse_url($relative, PHP_URL_HOST);
            abort_if($relativeHost !== ($base['host'] ?? null), 422, 'Stream referenced a different host.');

            return $relative;
        }

        $dir = rtrim(dirname($base['path'] ?? '/'), '/');
        $port = isset($base['port']) ? ':'.$base['port'] : '';

        return "{$base['scheme']}://{$base['host']}{$port}{$dir}/{$relative}";
    }

    private function rewritePlaylistUris(string $playlist, CctvCamera $cctvCamera): string
    {
        $proxyBase = route('cctv-cameras.hls-proxy', $cctvCamera->id);

        $lines = explode("\n", $playlist);

        foreach ($lines as &$line) {
            $trimmed = trim($line);
            if ($trimmed === '' || str_starts_with($trimmed, '#')) {
                continue;
            }
            $line = $proxyBase.'?path='.urlencode($trimmed);
        }

        return implode("\n", $lines);
    }
}
