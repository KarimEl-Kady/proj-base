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

Route::get('/health', HealthController::class)->name('api.health');
Route::get('/health/live', LivenessController::class)->name('api.health.live');
Route::get('/health/ready', HealthController::class)->name('api.health.ready');
