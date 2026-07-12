<?php

namespace App\Modules\Auth\Providers;

use App\Modules\Auth\Middleware\EnsureEmailIsVerified;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AuthServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Sanctum token lifetime follows the project-level auth config.
        config(['sanctum.expiration' => (int) config('project.auth.token_expiration', 1440)]);

        // Feature-flag-aware verification gate — annotate routes with
        // 'verified.feature' and PROJECT_FEATURE_EMAIL_VERIFICATION flips
        // enforcement globally (pass-through while the flag is off).
        $this->app['router']->aliasMiddleware('verified.feature', EnsureEmailIsVerified::class);

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
            return config('app.url').'/password/reset?token='.$token
                .'&email='.urlencode($notifiable->getEmailForPasswordReset());
        });
    }
}
