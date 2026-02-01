<?php

use App\Http\Controllers\Api\V1\SyncController;
use App\Http\Controllers\Api\V1\DashboardController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| V1 API Routes
|--------------------------------------------------------------------------
*/

Route::prefix('sync')->group(function () {
    Route::get('/range', [SyncController::class, 'syncRange']);
    // Queue a background sync for a range (remote API -> DB). Suitable for large ranges like full years.
    Route::get('/enqueue/range', [SyncController::class, 'enqueueSyncRange']);
    Route::get('/batch/{id}', [SyncController::class, 'batchStatus']);

    // Rebuild aggregated tables from already-synced `visits` (no external API calls)
    Route::get('/reaggregate/range', [SyncController::class, 'reaggregateRange']);
    Route::get('/{date?}', [SyncController::class, 'sync']);
});

Route::prefix('dashboard')->group(function () {
    Route::get('/stats', [DashboardController::class, 'getStats']);
    Route::get('/clinics', [DashboardController::class, 'getClinicBreakdown']);
});
