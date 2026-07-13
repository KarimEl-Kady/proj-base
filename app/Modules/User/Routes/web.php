<?php

/*
|--------------------------------------------------------------------------
| User Web Routes
|--------------------------------------------------------------------------
|
| Loaded under the "web" middleware group by CoreServiceProvider. User is
| API-first — no public web UI ships for PII, on purpose (managing users
| belongs behind the authenticated dashboard, not on public web routes).
|
| If this project grows a public-facing user page, protect it explicitly:
|
|     Route::prefix('users')->middleware('auth')->group(function () {
|         Route::get('/{user}', [ProfileController::class, 'show'])
|             ->name('users.show');
|     });
|
*/
