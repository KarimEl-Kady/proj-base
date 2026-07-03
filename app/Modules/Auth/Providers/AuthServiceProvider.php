<?php

namespace App\Modules\Auth\Providers;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Sanctum token lifetime follows the project-level auth config.
        config(['sanctum.expiration' => (int) config('project.auth.token_expiration', 1440)]);

        // API-first reset links: point at the frontend, not a Blade route.
        ResetPassword::createUrlUsing(function ($notifiable, string $token) {
            return config('app.url').'/password/reset?token='.$token
                .'&email='.urlencode($notifiable->getEmailForPasswordReset());
        });
    }
}
