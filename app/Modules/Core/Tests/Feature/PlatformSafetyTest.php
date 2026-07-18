<?php

namespace App\Modules\Core\Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class PlatformSafetyTest extends TestCase
{
    public function test_request_id_is_generated_and_valid_incoming_id_is_preserved(): void
    {
        $generated = $this->getJson('/api/health/live')->headers->get('X-Request-ID');
        $this->assertNotNull($generated);
        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $generated);

        $this->withHeader('X-Request-ID', 'request-12345678')
            ->getJson('/api/health/live')
            ->assertHeader('X-Request-ID', 'request-12345678');
    }

    public function test_invalid_configuration_fails_validation(): void
    {
        config([
            'database.default' => 'sqlsrv',
            'project.api.prefix' => 'nested/api',
            'project.health.queue_heartbeat_ttl' => 1,
            'project.pagination.unpaginated_cap' => -1,
            'project.tenancy.default_tenant.slug' => 'Not Valid',
            'project.tenancy.tenant_model' => \stdClass::class,
        ]);

        $this->artisan('project:validate')
            ->expectsOutputToContain('database.default')
            ->expectsOutputToContain('project.api.prefix')
            ->expectsOutputToContain('project.health.queue_heartbeat_ttl')
            ->expectsOutputToContain('project.pagination.unpaginated_cap')
            ->expectsOutputToContain('project.tenancy.default_tenant.slug')
            ->expectsOutputToContain('project.tenancy.tenant_model')
            ->assertFailed();
    }

    public function test_local_packages_cannot_import_application_classes(): void
    {
        $path = app_path('Vendor/BoundaryProbe/src/Leak.php');
        File::ensureDirectoryExists(dirname($path));
        File::put($path, "<?php\n\nuse App\\Models\\Tenant;\n");

        try {
            $this->artisan('module:boundaries')
                ->expectsOutputToContain('package:BoundaryProbe')
                ->assertFailed();
        } finally {
            File::deleteDirectory(app_path('Vendor/BoundaryProbe'));
        }
    }
}
