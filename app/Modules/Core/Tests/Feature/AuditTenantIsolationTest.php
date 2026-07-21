<?php

namespace App\Modules\Core\Tests\Feature;

use App\Models\Tenant;
use App\Modules\Core\Exceptions\MissingTenantContextException;
use App\Modules\Core\Models\AuditLog;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tests\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
class AuditTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    public static function setUpBeforeClass(): void
    {
        putenv('PROJECT_TENANCY_MODE=multi');
    }

    public static function tearDownAfterClass(): void
    {
        putenv('PROJECT_TENANCY_MODE');
    }

    public function test_audit_queries_cannot_cross_tenant_boundaries(): void
    {
        $tenantA = Tenant::query()->create(['name' => 'Acme', 'slug' => 'acme']);
        $tenantB = Tenant::query()->create(['name' => 'Globex', 'slug' => 'globex']);

        with_tenant($tenantA->id, fn () => User::query()->create([
            'name' => 'Alice',
            'email' => 'alice@example.com',
            'password' => 'secret-password',
        ]));
        with_tenant($tenantB->id, fn () => User::query()->create([
            'name' => 'Bob',
            'email' => 'bob@example.com',
            'password' => 'secret-password',
        ]));

        $this->assertSame(1, with_tenant($tenantA->id, fn () => AuditLog::query()->count()));
        $this->assertSame(1, with_tenant($tenantB->id, fn () => AuditLog::query()->count()));
        $this->assertSame(2, without_tenant_scope(fn () => AuditLog::query()->count()));
    }

    public function test_querying_without_a_tenant_throws_in_strict_mode_but_not_when_strict_is_disabled(): void
    {
        config(['project.tenancy.strict' => true]);

        $this->expectException(MissingTenantContextException::class);
        AuditLog::query()->count();
    }

    public function test_disabling_strict_mode_restores_fail_open_reads_for_audit_log_too(): void
    {
        config(['project.tenancy.strict' => false]);

        // Must not throw — PROJECT_TENANCY_STRICT=false is documented as a
        // global legacy fail-open switch; AuditLog's own scope used to
        // ignore it and throw regardless. Empty because no tenant-scoped
        // write has happened outside with_tenant() in this test.
        $this->assertSame(0, AuditLog::query()->count());
    }
}
