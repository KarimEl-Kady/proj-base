<?php

namespace Local\DataResponse;

use Illuminate\Support\ServiceProvider;

class DataResponseServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/data_response.php', 'data_response');
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/data_response.php' => config_path('data_response.php'),
        ], 'data-response-config');
    }
}
