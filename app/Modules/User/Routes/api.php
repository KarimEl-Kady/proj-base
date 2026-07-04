<?php

use App\Modules\User\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| User API Routes
|--------------------------------------------------------------------------
|
| Loaded under the "api" middleware group by CoreServiceProvider when
| project.route_attributes.enabled is false. Mirrors UserController's
| #[Prefix('api/v1/users')] / #[Middleware('api')] attributes.
|
*/

Route::prefix('api/v1/users')->group(function () {
    Route::get('/', [UserController::class, 'index'])->name('api.users.index');
    Route::post('/', [UserController::class, 'store'])->name('api.users.store');
    Route::get('/{user}', [UserController::class, 'show'])->name('api.users.show');
    Route::put('/{user}', [UserController::class, 'update'])->name('api.users.update');
    Route::delete('/{user}', [UserController::class, 'destroy'])->name('api.users.destroy');
});
