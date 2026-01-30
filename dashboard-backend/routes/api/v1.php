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
    Route::get('/{date?}', [SyncController::class, 'sync']);
});

Route::prefix('dashboard')->group(function () {
    Route::get('/stats', [DashboardController::class, 'getStats']);
    Route::get('/clinics', [DashboardController::class, 'getClinicBreakdown']);
});
