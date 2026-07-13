<?php

use App\Modules\Core\Controllers\Api\HealthController;
use App\Modules\Core\Controllers\Api\LivenessController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Core API Routes
|--------------------------------------------------------------------------
|
| Loaded under the "api" middleware group by CoreServiceProvider.
|
*/

Route::get('/api/health', HealthController::class)->name('api.health');
Route::get('/api/health/live', LivenessController::class)->name('api.health.live');
Route::get('/api/health/ready', HealthController::class)->name('api.health.ready');
