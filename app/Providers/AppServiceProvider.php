<?php

namespace App\Providers;

use App\Modules\Core\Support\MediaTenantResolver;
use Illuminate\Support\ServiceProvider;
use Local\Media\Contracts\TenantResolver;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(TenantResolver::class, MediaTenantResolver::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Package providers are auto-discovered after application providers
        // in some cached manifests; re-assert the host adapter at boot so the
        // package's safe NullTenantResolver can never win in the host app.
        $this->app->singleton(TenantResolver::class, MediaTenantResolver::class);
    }
}
