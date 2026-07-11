<?php

use App\Modules\Country\Controllers\Api\CountryController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Country API Routes
|--------------------------------------------------------------------------
|
| Loaded under the "api" middleware group by CoreServiceProvider.
|
*/

Route::prefix('api/v1/countries')->group(function () {
    // Reads are public reference data; writes require authentication.
    Route::get('/', [CountryController::class, 'index'])->name('api.countries.index');
    Route::get('/{country}', [CountryController::class, 'show'])->name('api.countries.show');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/', [CountryController::class, 'store'])->name('api.countries.store');
        Route::put('/{country}', [CountryController::class, 'update'])->name('api.countries.update');
        Route::delete('/{country}', [CountryController::class, 'destroy'])->name('api.countries.destroy');
    });
});
