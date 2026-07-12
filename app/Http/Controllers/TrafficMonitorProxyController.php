<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Reverse-proxies /traffic-monitor to the traffic-monitor-service Flask app
 * (see config('services.traffic_monitor.url')). Flask has no auth of its
 * own -- this controller sitting behind the `auth`+`verified` middleware on
 * the route is the only access control, and Flask must stay bound to
 * 127.0.0.1 so it's unreachable any other way.
 *
 * Streams the upstream body through in chunks rather than buffering it --
 * required for the MJPEG camera feed routes (multipart/x-mixed-replace never
 * ends), and harmless for the ordinary HTML page. A buffered Http::get()
 * would either hang forever trying to buffer an infinite MJPEG body, or die
 * at its timeout and cut the video.
 */
class TrafficMonitorProxyController extends Controller
{
    public function show(Request $request, string $path = ''): StreamedResponse
    {
        $base = rtrim(config('services.traffic_monitor.url'), '/');
        $url = "{$base}/{$path}";

        try {
            // connectTimeout still fails fast if Flask is down; timeout(0)
            // removes the overall cap so a long-lived MJPEG stream isn't cut.
            $upstream = Http::withOptions(['stream' => true])
                ->connectTimeout(3)
                ->timeout(0)
                ->get($url, $request->query());
        } catch (Throwable $e) {
            abort(502, 'Traffic monitor service is unavailable. Start it with `py traffic-monitor-service/app.py`.');
        }

        if ($upstream->failed()) {
            abort(502, "Traffic monitor service returned HTTP {$upstream->status()}.");
        }

        return response()->stream(function () use ($upstream) {
            $body = $upstream->toPsrResponse()->getBody();
            while (! $body->eof()) {
                echo $body->read(8192);
                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();
            }
        }, $upstream->status(), [
            'Content-Type' => $upstream->header('Content-Type', 'text/html'),
        ]);
    }
}
