# AGENTS.md

This file provides guidance to WARP (warp.dev) when working with code in this repository.

## Repo layout (big picture)
This repo contains two top-level apps:
- `dashboard-backend/`: Laravel 12 (PHP 8.2+) API + data sync/aggregation jobs.
- `dashboard-frontend/`: Vue 3 + Vite (CoreUI template) dashboard UI.

The frontend calls the backend API (currently hard-coded to `http://localhost:8000/api/v1/...`).

## Common commands

### Backend (Laravel) — `dashboard-backend/`
Run these from `dashboard-backend/`.

Setup (installs PHP deps, creates `.env`, generates key, runs migrations, installs Node deps, builds assets):
- `composer run setup`

Run the local dev stack (PHP server + queue listener + log tailing + Vite):
- `composer run dev`

Run tests:
- `composer run test`
- Single test file: `php artisan test tests/Feature/SomeTest.php`
- Filter by test name: `php artisan test --filter SomeTestName`

Format/lint (PHP):
- `vendor/bin/pint`

Frontend assets used by the backend (Vite/Tailwind):
- Dev: `npm run dev`
- Build: `npm run build`

### Frontend (Vue) — `dashboard-frontend/`
Run these from `dashboard-frontend/`.

Install deps:
- `npm install` (or `npm ci` if lockfile is up to date)

Dev server (Vite):
- `npm run dev` (configured to use port 3000)

Build / preview:
- `npm run build`
- `npm run preview`

Lint:
- `npm run lint`

## Backend architecture

### API routing/versioning
- `routes/api.php` mounts versioned route files under `/api/v1/...`.
- V1 routes live in `routes/api/v1.php`.

### Data sync pipeline (external API → local DB)
- Entry points:
  - `App\Http\Controllers\Api\V1\SyncController`
    - `GET /api/v1/sync/{date?}` (date accepts `YYYYMMDD` or `YYYY-MM-DD`)
    - `GET /api/v1/sync/range?start_date=...&end_date=...` (hard-limited to ~1 year)
- Core implementation:
  - `App\Services\SyncService::syncForDate($date)`
    - Fetches visit data from an external endpoint (currently built from `http://192.168.235.250/.../dashboard/{YYYYMMDD}`) using Basic Auth via `DASHBOARD_API_USERNAME` / `DASHBOARD_API_PASSWORD`.
    - Upserts raw records into `visits` and also updates “master” lookup tables (`clinics`, `departments`, `doctors`).
    - Writes a record to `sync_logs` for each sync run.
  - `App\Services\SyncService::updateAggregatedStats($date)`
    - Computes and upserts aggregated rows used by the dashboard UI:
      - `daily_dashboard_stats` (summary totals + category counts)
      - `clinic_stats` (per-clinic totals)

### Dashboard read API (aggregated tables → JSON)
- `App\Http\Controllers\Api\V1\DashboardController`
  - `GET /api/v1/dashboard/stats?start_date=...&end_date=...`
  - `GET /api/v1/dashboard/clinics?start_date=...&end_date=...`
- These endpoints read from the aggregated tables (`daily_dashboard_stats`, `clinic_stats`).
- The controller only auto-syncs *today’s* data if missing (historical ranges are expected to already be synced via the sync endpoints).

### Key tables/models
- Raw data:
  - `visits` (`App\Models\Visit`)
- Aggregates used by the UI:
  - `daily_dashboard_stats` (`App\Models\DailyDashboardStat`)
  - `clinic_stats` (`App\Models\ClinicStat`)
- Sync observability:
  - `sync_logs` (`App\Models\SyncLog`)
- Lookup/master tables:
  - `clinics`, `departments`, `doctors` (`App\Models\Clinic`, `Department`, `Doctor`)

### Local ad-hoc scripts
- `test_range_stats.php` / `test_range_clinics.php` bootstrap Laravel and call controller methods directly (useful for quick manual verification of JSON output without running the HTTP server).
- `resync_june.php` is a one-off date-range sync helper (it calls `app(SyncService::class)` and assumes it’s executed with Laravel bootstrapped).

## Frontend architecture
- Entrypoints:
  - `src/main.js` wires up Vue + Pinia + router + CoreUI.
  - `src/App.vue` renders the router.
- Routing:
  - `src/router/index.js` uses hash routing; main screens are under `/dashboard`.
- Data fetching/state:
  - `src/stores/dashboard.js` owns date-range selection and fetches stats/clinic breakdown from the backend.
  - Backend URLs are currently inlined (`http://localhost:8000/api/v1/...`), so keep backend running on port 8000 during local frontend work unless you update those call sites.
