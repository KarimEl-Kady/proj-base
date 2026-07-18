<?php

namespace Tests\Unit;

use App\Modules\Core\Support\ModuleRuntimeCache;
use Illuminate\Support\Facades\Artisan;
use RuntimeException;
use Tests\TestCase;

class ModuleRuntimeCacheTest extends TestCase
{
    public function test_it_clears_every_cache_affected_by_module_state(): void
    {
        Artisan::shouldReceive('call')->once()->ordered()->with('config:clear')->andReturn(0);
        Artisan::shouldReceive('call')->once()->ordered()->with('route:clear')->andReturn(0);
        Artisan::shouldReceive('call')->once()->ordered()->with('event:clear')->andReturn(0);

        ModuleRuntimeCache::clear();

        $this->addToAssertionCount(1);
    }

    public function test_it_fails_when_a_cache_cannot_be_cleared(): void
    {
        Artisan::shouldReceive('call')->once()->with('config:clear')->andReturn(1);

        $this->expectException(RuntimeException::class);

        ModuleRuntimeCache::clear();
    }
}
