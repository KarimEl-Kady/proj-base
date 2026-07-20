<?php

use App\Modules\Auth\Controllers\Api\AccountSecurityController;
use App\Modules\Auth\Controllers\Api\AuthController;
use App\Modules\Auth\Controllers\Api\EmailVerificationController;
use App\Modules\Auth\Controllers\Api\PasswordResetController;
use App\Modules\Auth\Controllers\Api\TokenController;
use App\Modules\Auth\Controllers\Api\TwoFactorController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Auth API Routes
|--------------------------------------------------------------------------
|
| Loaded under the "api" middleware group by CoreServiceProvider.
|
*/

Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register'])
        ->middleware('throttle:10,1')
        ->name('api.auth.register');

    Route::post('/login', [AuthController::class, 'login'])
        ->middleware('throttle:10,1')
        ->name('api.auth.login');

    Route::post('/logout', [AuthController::class, 'logout'])
        ->middleware('auth:sanctum')
        ->name('api.auth.logout');

    Route::get('/me', [AuthController::class, 'me'])
        ->middleware('auth:sanctum')
        ->name('api.auth.me');
});

Route::prefix('auth/password')->middleware('throttle:6,1')->group(function () {
    Route::post('/forgot', [PasswordResetController::class, 'forgot'])->name('api.auth.password.forgot');
    Route::post('/reset', [PasswordResetController::class, 'reset'])->name('api.auth.password.reset');
});

Route::prefix('auth/email')->group(function () {
    Route::post('/resend', [EmailVerificationController::class, 'resend'])
        ->middleware(['auth:sanctum', 'abilities:account:manage', 'throttle:6,1'])
        ->name('api.auth.email.resend');

    Route::get('/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
        ->middleware(['signed:relative', 'throttle:6,1'])
        ->name('verification.verify');
});

Route::prefix('auth/2fa')->middleware(['auth:sanctum', 'abilities:account:manage'])->group(function () {
    Route::post('/enable', [TwoFactorController::class, 'enable'])->name('api.auth.2fa.enable');

    Route::post('/confirm', [TwoFactorController::class, 'confirm'])
        ->middleware('throttle:6,1')
        ->name('api.auth.2fa.confirm');

    Route::post('/disable', [TwoFactorController::class, 'disable'])
        ->middleware('throttle:6,1')
        ->name('api.auth.2fa.disable');
});

Route::prefix('auth/account')->middleware(['auth:sanctum', 'abilities:account:manage', 'throttle:6,1'])->group(function () {
    Route::put('/email', [AccountSecurityController::class, 'changeEmail'])
        ->name('api.auth.account.email');
    Route::put('/password', [AccountSecurityController::class, 'changePassword'])
        ->name('api.auth.account.password');
});

Route::prefix('auth/tokens')->middleware(['auth:sanctum', 'abilities:account:manage'])->group(function () {
    Route::get('/', [TokenController::class, 'index'])->name('api.auth.tokens.index');
    Route::post('/', [TokenController::class, 'store'])->name('api.auth.tokens.store');
    Route::delete('/{id}', [TokenController::class, 'destroy'])->name('api.auth.tokens.destroy');
});
