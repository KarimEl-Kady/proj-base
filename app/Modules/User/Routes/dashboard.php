<?php

use App\Modules\User\Controllers\Web\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| User Dashboard Routes
|--------------------------------------------------------------------------
|
| Loaded under project.routes.dashboard (prefix "dashboard", middleware
| ["web", "auth"], name prefix "dashboard.") by CoreServiceProvider when
| project.route_attributes.enabled is false. Reuses the same Web
| controller/views as Routes/web.php — a real admin backoffice would
| swap in a dedicated Controllers/Dashboard controller instead.
|
| Final paths:  dashboard/users, dashboard/users/{user}, ...
| Final names:  dashboard.users.index, dashboard.users.show, ...
|
*/

Route::prefix('users')->group(function () {
    Route::get('/', [UserController::class, 'index'])->name('users.index');
    Route::get('/{user}', [UserController::class, 'show'])->name('users.show');
    Route::get('/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
    Route::put('/{user}', [UserController::class, 'update'])->name('users.update');
    Route::delete('/{user}', [UserController::class, 'destroy'])->name('users.destroy');
});
