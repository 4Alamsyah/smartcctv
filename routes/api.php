<?php

use App\Http\Controllers\Api\V1\CrmReportController;
use App\Http\Controllers\Api\V1\PatrolReportController;
use App\Http\Controllers\Api\V1\TrafficViolationController;
use App\Http\Controllers\CctvCameraController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes (v1)
|--------------------------------------------------------------------------
|
| Two client populations hit this file:
|  - The Python edge worker (RTSP/YOLO pipeline) authenticates with a
|    long-lived Sanctum personal access token scoped to the `edge:ingest`
|    ability. See database/seeders/EdgeWorkerTokenSeeder.php.
|  - The Vue/Inertia dashboard reads GeoJSON via the authenticated session
|    guard (it's same-origin, so plain `auth` works — no Sanctum SPA mode
|    needed).
*/

Route::prefix('v1')->group(function () {

    // --- Edge ML worker ingestion (Sanctum bearer token) ---------------
    Route::middleware(['auth:sanctum', 'ability:edge:ingest'])->group(function () {
        Route::post('/violations', [TrafficViolationController::class, 'store']);
        Route::get('/cctv-cameras/{code}', [CctvCameraController::class, 'showConfig']);
    });

    // --- CRM citizen complaint intake (public form + service token) ----
    Route::post('/crm-reports', [CrmReportController::class, 'store'])
        ->middleware(['throttle:crm-reports']);

    // --- Dashboard read APIs (authenticated dispatcher session) --------
    Route::middleware(['auth'])->group(function () {
        Route::get('/violations', [TrafficViolationController::class, 'index']);
        Route::get('/violations/{violation}', [TrafficViolationController::class, 'show']);
        Route::patch('/violations/{violation}/status', [TrafficViolationController::class, 'updateStatus']);

        Route::get('/crm-reports', [CrmReportController::class, 'index']);

        Route::get('/patrol-schedules/today', [PatrolReportController::class, 'today']);
        Route::post('/patrol-schedules/generate', [PatrolReportController::class, 'generate']);
    });
});
