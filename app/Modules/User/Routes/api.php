<?php

use App\Modules\User\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| User API Routes
|--------------------------------------------------------------------------
|
| Loaded under the "api" middleware group by CoreServiceProvider.
|
*/

// User records are PII — every endpoint requires authentication, and each
// action requires its own permission (see config/permission.php). Grant
// them via roles: php artisan permission:seed, then $user->assignRole('admin').
Route::prefix('users')->middleware('auth:sanctum')->group(function () {
    Route::get('/', [UserController::class, 'index'])->middleware('permission:users.view')->name('api.users.index');
    Route::post('/', [UserController::class, 'store'])->middleware('permission:users.create')->name('api.users.store');
    Route::get('/{user}', [UserController::class, 'show'])->middleware('permission:users.view')->name('api.users.show');
    Route::put('/{user}', [UserController::class, 'update'])->middleware('permission:users.update')->name('api.users.update');
    Route::delete('/{user}', [UserController::class, 'destroy'])->middleware('permission:users.delete')->name('api.users.destroy');
});
