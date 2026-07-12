<?php

use App\Http\Controllers\CctvCameraController;
use App\Http\Controllers\TrafficMonitorProxyController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

Route::get('dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

// Reverse-proxied to the traffic-monitor-service Flask app (see
// TrafficMonitorProxyController); {path?} also carries through any
// sub-requests the served page makes back to this same origin.
Route::get('traffic-monitor/{path?}', [TrafficMonitorProxyController::class, 'show'])
    ->where('path', '.*')
    ->middleware(['auth', 'verified'])
    ->name('traffic-monitor');

Route::resource('cctv-cameras', CctvCameraController::class)
    ->middleware(['auth', 'verified'])
    ->except('show');

Route::get('cctv-cameras/{cctv_camera}/check-connection', [CctvCameraController::class, 'checkConnection'])
    ->middleware(['auth', 'verified'])
    ->name('cctv-cameras.check-connection');

Route::get('cctv-cameras/{cctv_camera}/hls-proxy', [CctvCameraController::class, 'proxyHlsStream'])
    ->middleware(['auth', 'verified'])
    ->name('cctv-cameras.hls-proxy');

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
