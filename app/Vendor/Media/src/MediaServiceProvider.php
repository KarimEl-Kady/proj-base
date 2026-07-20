<?php

namespace Local\Media;

use Illuminate\Support\ServiceProvider;
use Local\Media\Contracts\TenantResolver;
use Local\Media\Services\MediaService;
use Local\Media\Support\NullTenantResolver;

class MediaServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/media.php', 'media');

        $this->app->singleton(TenantResolver::class, NullTenantResolver::class);
        $this->app->singleton(MediaService::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        $this->publishes([
            __DIR__.'/../config/media.php' => config_path('media.php'),
        ], 'media-config');
    }
}
