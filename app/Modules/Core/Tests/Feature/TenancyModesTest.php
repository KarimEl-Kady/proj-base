<?php

namespace App\Modules\Core\Tests\Feature;

use App\Models\Tenant;
use App\Modules\Core\Exceptions\MissingTenantContextException;
use App\Modules\Core\Exceptions\TenantContextMismatchException;
use App\Modules\User\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
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

    public function test_tenants_are_soft_deleted_for_data_retention(): void
    {
        $tenant = Tenant::create(['name' => 'Acme', 'slug' => 'acme']);

        $tenant->delete();

        $this->assertSoftDeleted('tenants', ['id' => $tenant->id]);
    }

    public function test_soft_deleted_default_tenant_stops_single_tenant_requests(): void
    {
        config(['project.tenancy.mode' => 'single']);
        Tenant::create(['name' => 'Default', 'slug' => 'default'])->delete();

        $this->getJson('/api/v1/countries')->assertStatus(400);
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
        // All health probes are real routes and stay tenantless.
        $this->getJson('/api/health')->assertOk();
        $this->getJson('/api/health/live')->assertOk();
        $this->getJson('/api/health/ready')->assertOk();
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

    // ── resolution caching ───────────────────────────────────────────

    public function test_multi_mode_resolution_is_cached_after_the_first_request(): void
    {
        config([
            'project.tenancy.mode' => 'multi',
            'project.tenancy.tenant_identification' => 'header',
        ]);

        Tenant::create(['name' => 'Acme', 'slug' => 'acme']);

        $this->getJson('/api/v1/countries', ['X-Tenant-ID' => 'acme'])->assertOk();

        // Second request must resolve from cache — zero tenants-table queries.
        $this->assertSame(
            0,
            $this->tenantQueriesDuring(
                fn () => $this->getJson('/api/v1/countries', ['X-Tenant-ID' => 'acme'])->assertOk()
            )
        );
    }

    public function test_deactivating_a_tenant_takes_effect_despite_the_cache(): void
    {
        config([
            'project.tenancy.mode' => 'multi',
            'project.tenancy.tenant_identification' => 'header',
        ]);

        $tenant = Tenant::create(['name' => 'Acme', 'slug' => 'acme']);

        $this->getJson('/api/v1/countries', ['X-Tenant-ID' => 'acme'])->assertOk();

        // The model write flushes the cached resolution — no TTL wait.
        $tenant->update(['is_active' => false]);

        $this->getJson('/api/v1/countries', ['X-Tenant-ID' => 'acme'])->assertStatus(400);
    }

    public function test_renaming_a_tenant_slug_flushes_the_stale_cache_key(): void
    {
        config([
            'project.tenancy.mode' => 'multi',
            'project.tenancy.tenant_identification' => 'header',
        ]);

        $tenant = Tenant::create(['name' => 'Acme', 'slug' => 'acme']);

        $this->getJson('/api/v1/countries', ['X-Tenant-ID' => 'acme'])->assertOk();

        $tenant->update(['slug' => 'acme-corp']);

        // Old identifier no longer resolves; the new one does.
        $this->getJson('/api/v1/countries', ['X-Tenant-ID' => 'acme'])->assertStatus(400);
        $this->getJson('/api/v1/countries', ['X-Tenant-ID' => 'acme-corp'])->assertOk();
    }

    public function test_single_mode_kill_switch_works_despite_the_cache(): void
    {
        config(['project.tenancy.mode' => 'single']);

        $this->getJson('/api/v1/countries')->assertOk();

        Tenant::where('slug', 'default')->first()->update(['is_active' => false]);

        $this->getJson('/api/v1/countries')->assertStatus(400);
    }

    public function test_resolution_cache_can_be_disabled(): void
    {
        config([
            'project.tenancy.mode' => 'multi',
            'project.tenancy.tenant_identification' => 'header',
            'project.tenancy.cache.enabled' => false,
        ]);

        Tenant::create(['name' => 'Acme', 'slug' => 'acme']);

        $this->getJson('/api/v1/countries', ['X-Tenant-ID' => 'acme'])->assertOk();

        // Cache off — every request goes to the tenants table.
        $this->assertGreaterThan(
            0,
            $this->tenantQueriesDuring(
                fn () => $this->getJson('/api/v1/countries', ['X-Tenant-ID' => 'acme'])->assertOk()
            )
        );
    }

    /**
     * Number of queries against the tenants table executed by $callback.
     */
    protected function tenantQueriesDuring(callable $callback): int
    {
        $count = 0;

        DB::listen(function (QueryExecuted $query) use (&$count) {
            if (str_contains($query->sql, 'tenants')) {
                $count++;
            }
        });

        $callback();

        return $count;
    }

    // ── strict mode & context helpers ────────────────────────────────

    public function test_strict_mode_fails_closed_on_queries_without_tenant_context(): void
    {
        config(['project.tenancy.mode' => 'multi']);

        $this->expectException(MissingTenantContextException::class);

        User::query()->get();
    }

    public function test_strict_mode_fails_closed_on_creates_without_tenant_context(): void
    {
        config(['project.tenancy.mode' => 'multi']);

        $this->expectException(MissingTenantContextException::class);

        User::factory()->create();
    }

    public function test_with_tenant_establishes_context_and_restores_the_previous_one(): void
    {
        config(['project.tenancy.mode' => 'multi']);
        $this->addTenantColumnToUsers();

        Context::add('tenant_id', 3);

        $user = with_tenant(9, fn () => User::factory()->create());

        $this->assertSame(9, (int) $user->getAttribute('tenant_id'));
        $this->assertSame(3, tenant_id());
    }

    public function test_without_tenant_scope_allows_deliberate_cross_tenant_access(): void
    {
        config(['project.tenancy.mode' => 'multi']);
        $this->addTenantColumnToUsers();

        with_tenant(7, fn () => User::factory()->create());
        with_tenant(8, fn () => User::factory()->create());

        // Scoped: each tenant sees only its own row.
        $this->assertSame(1, with_tenant(7, fn () => User::query()->count()));

        // Explicit bypass: all rows, no exception, no stamping.
        $this->assertSame(2, without_tenant_scope(fn () => User::query()->count()));
    }

    public function test_strict_mode_rejects_an_explicit_tenant_without_context(): void
    {
        config(['project.tenancy.mode' => 'multi']);
        $this->addTenantColumnToUsers();

        $this->expectException(MissingTenantContextException::class);

        $user = User::factory()->make();
        $user->setAttribute('tenant_id', 42);
        $user->save();
    }

    public function test_active_tenant_rejects_a_conflicting_preset_tenant_column(): void
    {
        config(['project.tenancy.mode' => 'multi']);
        $this->addTenantColumnToUsers();

        Context::add('tenant_id', 7);

        $this->expectException(TenantContextMismatchException::class);

        $user = User::factory()->make();
        $user->setAttribute('tenant_id', 42);
        $user->save();
    }

    public function test_disabling_strict_mode_restores_legacy_fail_open_behavior(): void
    {
        config([
            'project.tenancy.mode' => 'multi',
            'project.tenancy.strict' => false,
        ]);
        $this->addTenantColumnToUsers();

        $user = User::factory()->create();

        $this->assertNull($user->fresh()->getAttribute('tenant_id'));
        $this->assertSame(1, User::query()->count());
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
