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

// User records are PII — every endpoint requires authentication. Index,
// store, show, and destroy each require their own permission (see
// config/permission.php; grant via roles: php artisan permission:seed, then
// $user->assignRole('admin')). update deliberately carries no permission
// middleware: UserPolicy::update() (record-level, auto-discovered) allows
// either users.update or the actor editing their own record, so a plain
// authenticated user can always update their own profile through this
// endpoint. destroy keeps the users.delete gate at the route level *and*
// UserPolicy::delete() denies self-deletion outright, even for an admin.
Route::prefix('users')->middleware('auth:sanctum')->group(function () {
    Route::get('/', [UserController::class, 'index'])->middleware('permission:users.view')->name('api.users.index');
    Route::post('/', [UserController::class, 'store'])->middleware('permission:users.create')->name('api.users.store');
    Route::get('/{user}', [UserController::class, 'show'])->middleware('permission:users.view')->name('api.users.show');
    Route::put('/{user}', [UserController::class, 'update'])->name('api.users.update');
    Route::delete('/{user}', [UserController::class, 'destroy'])->middleware('permission:users.delete')->name('api.users.destroy');
});
