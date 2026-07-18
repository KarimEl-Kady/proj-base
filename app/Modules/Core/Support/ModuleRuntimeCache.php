<?php

namespace App\Modules\Core\Support;

use Illuminate\Support\Facades\Artisan;
use RuntimeException;

class ModuleRuntimeCache
{
    public static function clear(): void
    {
        foreach (['config:clear', 'route:clear', 'event:clear'] as $command) {
            if (Artisan::call($command) !== 0) {
                throw new RuntimeException("Unable to clear runtime cache with [{$command}].");
            }
        }
    }
}
