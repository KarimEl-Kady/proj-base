<?php

use App\Modules\City\Controllers\Api\CityController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| City API Routes
|--------------------------------------------------------------------------
|
| Loaded under the "api" middleware group by CoreServiceProvider.
|
*/

Route::prefix('api/v1/cities')->group(function () {
    Route::get('/', [CityController::class, 'index'])->name('api.cities.index');
    Route::post('/', [CityController::class, 'store'])->name('api.cities.store');
    Route::get('/{city}', [CityController::class, 'show'])->name('api.cities.show');
    Route::put('/{city}', [CityController::class, 'update'])->name('api.cities.update');
    Route::delete('/{city}', [CityController::class, 'destroy'])->name('api.cities.destroy');
});
