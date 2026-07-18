<?php

namespace App\Modules\Core\Tests\Feature;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tests\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
class ApiPrefixTest extends TestCase
{
    use RefreshDatabase;

    public static function setUpBeforeClass(): void
    {
        putenv('PROJECT_API_PREFIX=service');
        putenv('PROJECT_API_VERSION=v9');
        putenv('PROJECT_TENANCY_MODE=multi');
        putenv('PROJECT_TENANT_IDENTIFICATION=header');
    }

    public static function tearDownAfterClass(): void
    {
        putenv('PROJECT_API_PREFIX');
        putenv('PROJECT_API_VERSION');
        putenv('PROJECT_TENANCY_MODE');
        putenv('PROJECT_TENANT_IDENTIFICATION');
    }

    public function test_prefix_and_version_apply_to_every_module_route(): void
    {
        Tenant::query()->create(['name' => 'Acme', 'slug' => 'acme']);

        $this->getJson('/api/v1/countries')->assertNotFound();
        $this->getJson('/service/v9/countries', ['X-Tenant-ID' => 'acme'])->assertOk();
        $this->getJson('/service/health/live')->assertOk();
    }
}
