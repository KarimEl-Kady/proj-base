<?php

use App\Modules\Geo\Controllers\Api\CityController;
use App\Modules\Geo\Controllers\Api\CountryController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Geo API Routes
|--------------------------------------------------------------------------
|
| Loaded under the "api" middleware group by CoreServiceProvider. Country
| and City are one module: City::belongsTo(Country), Country::hasMany(City),
| and geo:seed orchestrates both seeders together — genuinely one bounded
| context, not two modules that happen to reference each other.
|
*/

Route::prefix('countries')->group(function () {
    // Reads are public reference data; writes require authentication.
    Route::get('/', [CountryController::class, 'index'])->name('api.countries.index');
    Route::get('/{country}', [CountryController::class, 'show'])->name('api.countries.show');

    Route::middleware(['auth:sanctum', 'abilities:api', 'permission:countries.manage'])->group(function () {
        Route::post('/', [CountryController::class, 'store'])->name('api.countries.store');
        Route::put('/{country}', [CountryController::class, 'update'])->name('api.countries.update');
        Route::delete('/{country}', [CountryController::class, 'destroy'])->name('api.countries.destroy');
    });
});

Route::prefix('cities')->group(function () {
    // Reads are public reference data; writes require authentication.
    Route::get('/', [CityController::class, 'index'])->name('api.cities.index');
    Route::get('/{city}', [CityController::class, 'show'])->name('api.cities.show');

    Route::middleware(['auth:sanctum', 'abilities:api', 'permission:cities.manage'])->group(function () {
        Route::post('/', [CityController::class, 'store'])->name('api.cities.store');
        Route::put('/{city}', [CityController::class, 'update'])->name('api.cities.update');
        Route::delete('/{city}', [CityController::class, 'destroy'])->name('api.cities.destroy');
    });
});
