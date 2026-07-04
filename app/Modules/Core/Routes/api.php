<?php

use App\Modules\Core\Controllers\Api\HealthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Core API Routes
|--------------------------------------------------------------------------
|
| Loaded under the "api" middleware group by CoreServiceProvider when
| project.route_attributes.enabled is false. Mirrors HealthController's
| #[Prefix('api/health')] / #[Get('/', name: 'api.health')] attributes.
|
*/

Route::get('/api/health', HealthController::class)->name('api.health');
