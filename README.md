# SmartCCTV

Traffic monitoring and violation-detection platform for DISHUB (Dinas
Perhubungan) traffic camera networks. CCTV streams are watched for busway-lane
intrusions and illegal parking, violations are logged with an evidence frame
and license plate read, and a live dispatcher dashboard shows what's
happening in real time.

The system is three cooperating services, not one app:

```
┌─────────────────────┐        RTSP/HLS         ┌──────────────────┐
│   edge-worker/       │ ───────────────────────▶│   CCTV cameras    │
│   (Python)           │                          └──────────────────┘
│   YOLO detection +   │
│   stationary/geofence│  POST /api/v1/violations
│   tracking per camera│ ────────────────────────┐
└──────────────────────┘                          │
                                                    ▼
┌──────────────────────────────────────────────────────────────────┐
│  Laravel (this repo's PHP app)                                    │
│  - Postgres/PostGIS: cameras, violations, CRM reports, patrols    │
│  - Vue/Inertia dashboard: CCTV management, geofence drawing        │
│  - Reverb (WebSocket): broadcasts new violations live              │
│  - Reverse-proxies /traffic-monitor to traffic-monitor-service     │
└──────────────────────────────────────────────────────────────────┘
                                                    ▲
                              GET /cctv-cameras/{code}  (decrypt rtsp_url)
                                                    │
┌──────────────────────────────────────────────────┴───────────────┐
│  traffic-monitor-service/ (Python/Flask)                          │
│  Live violation map (Reverb) + per-camera YOLO-annotated video     │
└─────────────────────────────────────────────────────────────────┘
```

## Features

- **CCTV Management** — CRUD for cameras (`/cctv-cameras`): RTSP or HLS stream
  URL (encrypted at rest), live preview in-browser (HLS proxied through
  Laravel to dodge CORS), connectivity check, and a click-to-draw map for the
  busway-lane / no-parking geofence polygon used to scope detection.
- **Edge detection** (`edge-worker/`) — one process per camera: YOLO + ByteTrack
  vehicle tracking, stationary-duration measurement, geofence containment
  check, Groq vision fallback for plate OCR, POSTs confirmed violations to
  Laravel.
- **Traffic Monitor dashboard** (`traffic-monitor-service/`) — live map of
  pending violations (heatmap + markers, updates over Reverb with no refresh)
  plus a live per-camera video panel with its own YOLO detection overlay.
- **CRM intake** — citizen complaint text is classified/summarized by Gemini
  (`App\Services\Ai\GeminiExtractionService`) into category/severity/location.
- **Patrol dispatch planning** — clusters recent violation + CRM hotspots
  (`App\Services\Spatial\HotspotClusterService`) and asks Gemini to propose a
  prioritized patrol schedule (`App\Services\Ai\GeminiReportService`).

## Prerequisites

- PHP 8.2+, Composer
- Node.js 18+
- Python 3.11 (shared by `edge-worker/` and `traffic-monitor-service/` — no
  virtualenvs in this repo, both install into the same global site-packages)
- PostgreSQL with the **PostGIS** extension (all spatial columns depend on it)
- Redis (optional — swap `CACHE_STORE`/`SESSION_DRIVER` in `.env` if unavailable)

## Setup

### 1. Laravel

```bash
composer install
cp .env.example .env   # then fill in DB_*, APP_KEY, etc.
php artisan key:generate
php artisan migrate
```

`.env` needs (see the file for the full annotated list):
- `DB_*` — Postgres connection (PostGIS must already be installed on that server)
- `REVERB_*` / `VITE_REVERB_*` — WebSocket broadcasting config
- `GEMINI_API_KEY`, `GROQ_API_KEY` — used by the CRM/patrol AI features and
  the edge worker's plate-OCR fallback, respectively

### 2. Frontend

```bash
npm install
```

### 3. Service accounts for the Python processes

The edge worker and traffic-monitor-service each authenticate to Laravel's
API with their own Sanctum token (scoped to the `edge:ingest` ability —
enough to read a camera's decrypted `rtsp_url`/geofence and, for the edge
worker, POST violations):

```bash
php artisan db:seed --class="Database\Seeders\EdgeWorkerTokenSeeder"
php artisan db:seed --class="Database\Seeders\TrafficMonitorServiceTokenSeeder"
```

Each prints a token **once** — copy it into the corresponding service's `.env`
(`LARAVEL_API_TOKEN`).

### 4. `edge-worker/` (one process per camera)

```bash
cd edge-worker
py -m pip install -r requirements.txt
cp .env.example .env   # CAMERA_CODE must match a code in cctv_cameras;
                        # rtsp_url, zone_type and the geofence are fetched
                        # from Laravel at startup, not set here
py main.py
```

### 5. `traffic-monitor-service/`

```bash
cd traffic-monitor-service
py -m pip install -r requirements.txt
# .env already present in this repo checkout; fill in LARAVEL_API_TOKEN
# from step 3 if it's blank
```

Started automatically by `composer run dev` (see below), or standalone with
`py app.py`. Must stay bound to `127.0.0.1` — it has no auth of its own,
Laravel's reverse proxy is the only intended caller.

## Running everything

```bash
composer run dev
```

Runs, concurrently: `php artisan serve`, `php artisan queue:listen`,
`php artisan reverb:start`, `npm run dev`, and `traffic-monitor-service`.

The edge worker isn't included in that script — it's a one-per-camera
long-running process you start separately per deployed camera:

```bash
cd edge-worker && py main.py
```

## Key things to know before touching this repo

- **`cctv_cameras.rtsp_url` is encrypted at rest** (Eloquent `encrypted`
  cast). Neither Python service can read it via direct Postgres access — both
  fetch it decrypted through `GET /api/v1/cctv-cameras/{code}`
  (`CctvCameraController::showConfig`).
- **Route caching bites here** — after editing `routes/*.php`, run
  `php artisan route:clear` if a route 404s/uses a stale definition.
- **The Traffic Monitor sidebar link must stay `external: true`**
  (`resources/js/components/AppSidebar.vue`) — that route is reverse-proxied
  to Flask, not an Inertia response, so Inertia's `<Link>` can't navigate to
  it (see `NavItem['external']` in `resources/js/types/index.ts` for why).
- **Geofence-less cameras aren't blind** — if `lane_geofence` is unset for a
  camera, the edge worker treats the whole frame as in-bounds rather than
  reporting zero violations, so newly added cameras still work before anyone
  draws their polygon.
