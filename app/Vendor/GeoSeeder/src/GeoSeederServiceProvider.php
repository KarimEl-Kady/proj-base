<?php

namespace Local\GeoSeeder;

use Illuminate\Support\ServiceProvider;

class GeoSeederServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/geo_seeder.php', 'geo_seeder');

        $this->app->singleton(GeoDataRepository::class);
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/geo_seeder.php' => config_path('geo_seeder.php'),
        ], 'geo-seeder-config');
    }
}
