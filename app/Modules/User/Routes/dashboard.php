<?php

/*
|--------------------------------------------------------------------------
| User Dashboard Routes
|--------------------------------------------------------------------------
|
| Loaded under project.routes.dashboard (prefix "dashboard", middleware
| ["web", "auth"], name prefix "dashboard.") by CoreServiceProvider.
|
| Nothing ships here by default — the dashboard middleware only proves the
| visitor is *authenticated*, and user management additionally needs
| *authorization*. When this project builds its backoffice, add a dedicated
| dashboard controller + views and gate every action with the users.*
| permissions, mirroring the API routes:
|
|     Route::prefix('users')->group(function () {
|         Route::get('/', [DashboardUserController::class, 'index'])
|             ->middleware('permission:users.view')->name('users.index');
|         Route::delete('/{user}', [DashboardUserController::class, 'destroy'])
|             ->middleware('permission:users.delete')->name('users.destroy');
|     });
|
*/
