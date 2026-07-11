<?php

namespace App\Modules\Core\Tests\Feature;

use App\Models\Tenant;
use App\Modules\User\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * The three tenancy modes (project.tenancy.mode): "none", "single", "multi".
 * Covers the helpers, TenantMiddleware resolution per mode, and
 * HasTenantScope stamping/scoping.
 */
class TenancyModesTest extends TestCase
{
    use RefreshDatabase;

    // ── helpers ──────────────────────────────────────────────────────

    public function test_helpers_report_each_mode(): void
    {
        config(['project.tenancy.mode' => 'none']);
        $this->assertSame('none', tenancy_mode());
        $this->assertFalse(has_tenancy());
        $this->assertFalse(is_single_tenant());
        $this->assertFalse(is_multi_tenant());

        config(['project.tenancy.mode' => 'single']);
        $this->assertSame('single', tenancy_mode());
        $this->assertTrue(has_tenancy());
        $this->assertTrue(is_single_tenant());
        $this->assertFalse(is_multi_tenant());

        config(['project.tenancy.mode' => 'multi']);
        $this->assertSame('multi', tenancy_mode());
        $this->assertTrue(has_tenancy());
        $this->assertFalse(is_single_tenant());
        $this->assertTrue(is_multi_tenant());
    }

    public function test_unknown_mode_values_behave_as_none(): void
    {
        config(['project.tenancy.mode' => 'banana']);

        $this->assertSame('none', tenancy_mode());
        $this->assertFalse(has_tenancy());
    }

    // ── middleware: none ─────────────────────────────────────────────

    public function test_none_mode_requests_pass_without_a_tenant(): void
    {
        config(['project.tenancy.mode' => 'none']);

        $this->getJson('/api/health')->assertOk();

        $this->assertNull(tenant_id());
        $this->assertSame(0, Tenant::count());
    }

    // ── middleware: single ───────────────────────────────────────────

    public function test_single_mode_runs_requests_under_the_default_tenant(): void
    {
        config(['project.tenancy.mode' => 'single']);

        $this->getJson('/api/v1/countries')->assertOk();

        $tenant = Tenant::where('slug', 'default')->first();
        $this->assertNotNull($tenant, 'The default tenant should be created on first use.');
        $this->assertSame($tenant->id, tenant_id());
    }

    public function test_single_mode_default_tenant_is_created_once(): void
    {
        config(['project.tenancy.mode' => 'single']);

        $this->getJson('/api/v1/countries')->assertOk();
        $this->getJson('/api/v1/countries')->assertOk();

        $this->assertSame(1, Tenant::count());
    }

    public function test_single_mode_rejects_requests_when_default_tenant_is_deactivated(): void
    {
        config(['project.tenancy.mode' => 'single']);

        Tenant::create(['name' => 'Default', 'slug' => 'default', 'is_active' => false]);

        $this->getJson('/api/v1/countries')->assertStatus(400);
    }

    public function test_single_mode_default_tenant_is_configurable(): void
    {
        config([
            'project.tenancy.mode' => 'single',
            'project.tenancy.default_tenant' => ['name' => 'Acme', 'slug' => 'acme'],
        ]);

        $this->getJson('/api/v1/countries')->assertOk();

        $this->assertNotNull(Tenant::where('slug', 'acme')->where('name', 'Acme')->first());
    }

    // ── middleware: multi ────────────────────────────────────────────

    public function test_multi_mode_resolves_the_tenant_from_the_header(): void
    {
        config([
            'project.tenancy.mode' => 'multi',
            'project.tenancy.tenant_identification' => 'header',
        ]);

        $tenant = Tenant::create(['name' => 'Acme', 'slug' => 'acme']);

        $this->getJson('/api/v1/countries', ['X-Tenant-ID' => 'acme'])->assertOk();

        $this->assertSame($tenant->id, tenant_id());
    }

    public function test_multi_mode_rejects_unidentifiable_requests(): void
    {
        config([
            'project.tenancy.mode' => 'multi',
            'project.tenancy.tenant_identification' => 'header',
        ]);

        $this->getJson('/api/v1/countries')->assertStatus(400);
    }

    public function test_multi_mode_exempt_paths_skip_tenant_resolution(): void
    {
        config([
            'project.tenancy.mode' => 'multi',
            'project.tenancy.tenant_identification' => 'header',
        ]);

        $this->getJson('/api/health')->assertOk();
    }

    public function test_multi_mode_exempt_paths_support_wildcards(): void
    {
        config([
            'project.tenancy.mode' => 'multi',
            'project.tenancy.tenant_identification' => 'header',
            'project.tenancy.exempt_paths' => ['api/health*'],
        ]);

        // The wildcard exempts both the base path and any sub-paths —
        // none of them should be rejected with 400 (tenant required).
        // /api/health is a real route (200); sub-paths may 404 but must
        // never hit the 400 tenant-required gate.
        $this->getJson('/api/health')->assertOk();
        $this->getJson('/api/health/live')->assertNotFound();   // route missing, but NOT 400
        $this->getJson('/api/health/ready')->assertNotFound();  // route missing, but NOT 400
    }

    public function test_inactive_tenant_is_rejected(): void
    {
        config([
            'project.tenancy.mode' => 'multi',
            'project.tenancy.tenant_identification' => 'header',
        ]);

        Tenant::create(['name' => 'Disabled Corp', 'slug' => 'disabled-corp', 'is_active' => false]);

        $this->getJson('/api/v1/countries', ['X-Tenant-ID' => 'disabled-corp'])
            ->assertStatus(400);
    }

    public function test_active_tenant_is_accepted(): void
    {
        config([
            'project.tenancy.mode' => 'multi',
            'project.tenancy.tenant_identification' => 'header',
        ]);

        $tenant = Tenant::create(['name' => 'Active Corp', 'slug' => 'active-corp', 'is_active' => true]);

        $this->getJson('/api/v1/countries', ['X-Tenant-ID' => 'active-corp'])->assertOk();

        $this->assertSame($tenant->id, tenant_id());
    }

    // ── HasTenantScope ───────────────────────────────────────────────

    public function test_scope_stamps_and_filters_by_tenant_when_tenancy_is_active(): void
    {
        config(['project.tenancy.mode' => 'single']);
        $this->addTenantColumnToUsers();

        Context::add('tenant_id', 7);
        $mine = User::factory()->create();

        Context::add('tenant_id', 8);
        User::factory()->create();

        Context::add('tenant_id', 7);
        $this->assertSame(7, (int) $mine->fresh()->getAttribute('tenant_id'));
        $this->assertSame([$mine->id], User::query()->pluck('id')->all());
    }

    public function test_scope_is_inert_in_none_mode(): void
    {
        config(['project.tenancy.mode' => 'none']);
        $this->addTenantColumnToUsers();

        Context::add('tenant_id', 7);
        $user = User::factory()->create();

        $this->assertNull($user->fresh()->getAttribute('tenant_id'));
        $this->assertSame(1, User::query()->count());
    }

    /**
     * The test database migrates under the suite's default mode ("none"),
     * where tenantColumn() is a no-op — add the column by hand to exercise
     * the scope.
     */
    protected function addTenantColumnToUsers(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('tenant_id')->nullable()->index();
        });
    }
}
