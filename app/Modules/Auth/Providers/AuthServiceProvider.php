<?php

namespace App\Modules\Auth\Providers;

use App\Modules\Auth\Middleware\EnsureEmailIsVerified;
use App\Modules\Auth\Support\UserUuidPasswordBrokerManager;
use App\Modules\Core\Support\TenantUrl;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AuthServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // PasswordResetServiceProvider is deferred and may be loaded while
        // other providers boot. Replace both service keys only after every
        // provider has booted, then remove their deferred mappings so neither
        // can restore Laravel's email-keyed token repository later.
        $this->app->booted(function (Application $app): void {
            $app->removeDeferredServices([
                'auth.password',
                'auth.password.broker',
            ]);
            $app->forgetInstance('auth.password');
            $app->forgetInstance('auth.password.broker');
            $app->singleton(
                'auth.password',
                fn ($app) => new UserUuidPasswordBrokerManager($app),
            );
            $app->bind(
                'auth.password.broker',
                fn ($app) => $app->make('auth.password')->broker(),
            );
            Facade::clearResolvedInstance('auth.password');
        });
    }

    public function boot(): void
    {
        // Sanctum token lifetime follows the project-level auth config.
        config(['sanctum.expiration' => (int) config('project.auth.token_expiration', 1440)]);

        // Feature-flag-aware verification gate — annotate routes with
        // 'verified.feature' and PROJECT_FEATURE_EMAIL_VERIFICATION flips
        // enforcement globally (pass-through while the flag is off).
        $this->app['router']->aliasMiddleware('verified.feature', EnsureEmailIsVerified::class);

        VerifyEmail::createUrlUsing(function ($notifiable) {
            return TenantUrl::temporarySignedRoute(
                'verification.verify',
                now()->addMinutes((int) config('auth.verification.expire', 60)),
                [
                    'id' => $notifiable->getKey(),
                    'hash' => sha1($notifiable->getEmailForVerification()),
                ],
            );
        });

        // One password policy for the whole project (register, reset, and any
        // future form): strict in production, developer-friendly elsewhere.
        // uncompromised() checks the haveibeenpwned range API — production only,
        // so tests and offline dev never make network calls.
        Password::defaults(function () {
            $password = Password::min(8);

            return $this->app->isProduction()
                ? $password->mixedCase()->numbers()->uncompromised()
                : $password;
        });

        // API-first reset links: point at the frontend, not a Blade route.
        ResetPassword::createUrlUsing(function ($notifiable, string $token) {
            return TenantUrl::frontend('/password/reset', [
                'token' => $token,
                'email' => $notifiable->getEmailForPasswordReset(),
            ]);
        });
    }
}
