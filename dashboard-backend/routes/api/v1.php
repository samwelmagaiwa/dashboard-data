<?php

use App\Http\Controllers\Api\V1\SyncController;
use App\Http\Controllers\Api\V1\DashboardController;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\V1\AuthController;

/*
|--------------------------------------------------------------------------
| V1 API Routes
|--------------------------------------------------------------------------
*/

// Public Auth Routes
Route::post('/login', [AuthController::class, 'login']);

// Public Dashboard Routes (No Authentication Required)
Route::prefix('dashboard')->group(function () {
    Route::get('/stats', [DashboardController::class, 'getStats']);
    Route::get('/clinics', [DashboardController::class, 'getClinicBreakdown']);
    Route::get('/gaps', [DashboardController::class, 'getGaps']);
    Route::get('/pie-stats', [DashboardController::class, 'getPieStats']);
    Route::get('/chart-stats', [DashboardController::class, 'getComparisonStats']);
});

// Public Sync Routes (No Authentication Required)
Route::prefix('sync')->group(function () {
    Route::get('/range', [SyncController::class, 'syncRange']);
    Route::get('/enqueue/range', [SyncController::class, 'enqueueSyncRange']);
    Route::get('/batch/{id}', [SyncController::class, 'batchStatus']);
    Route::get('/reaggregate/range', [SyncController::class, 'reaggregateRange']);
    Route::get('/trigger/{date?}', [SyncController::class, 'triggerSync']);
    Route::get('/{date?}', [SyncController::class, 'sync']);
});

// Protected Routes (Authentication Required)
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // Reports
    Route::get('/reports/pending', [ReportsController::class, 'pending']);

    // Profile Routes
    Route::prefix('profile')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\V1\ProfileController::class, 'show']);
        Route::put('/', [\App\Http\Controllers\Api\V1\ProfileController::class, 'update']);
    });
});
