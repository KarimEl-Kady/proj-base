<?php

namespace Tests;

use App\Models\Tenant;
use App\Modules\Core\Support\Tenancy;
use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

abstract class TestCase extends BaseTestCase
{
    /**
     * Lazily resolved and memoized per test method (a fresh TestCase
     * instance per test), so repeated withTestTenant()/actingAsUser() calls
     * within one test land in the same tenant instead of each minting its
     * own — with_tenant() restores the previous Context value once its
     * callback returns, so nothing else makes that consistency automatic.
     */
    protected ?int $testTenantId = null;

    /**
     * Authenticate a fresh user for auth:sanctum-protected endpoints.
     * Pass permission name(s) to also authorize permission:-gated routes
     * (e.g. 'countries.manage', or ['users.view', 'users.update']).
     *
     * Tenancy-safe: when tenancy is active and no tenant is already in
     * Context, the user is created under one — see withTestTenant() — so
     * module test suites that don't care about tenancy specifically keep
     * passing whichever mode the project runs in. Tests that assert
     * cross-tenant behavior still want their own explicit
     * with_tenant()/request headers (see MultiTenantIdentityTest) rather
     * than relying on this default.
     *
     * Resolves the model from auth config instead of importing it so module
     * tests can call this without creating a cross-module dependency
     * (module:boundaries scans test files too).
     */
    protected function actingAsUser(string|array $permissions = [], int|string|null $tenantId = null): Authenticatable
    {
        $model = config('auth.providers.users.model');

        $user = $this->withTestTenant(
            $tenantId,
            fn () => $model::factory()->create(),
        );

        if ($permissions !== [] && method_exists($user, 'givePermissionTo')) {
            $user->givePermissionTo($permissions);
        }

        Sanctum::actingAs($user);

        return $user;
    }

    /**
     * Run $callback under a tenant, the way a real request would via
     * TenantMiddleware — for test setup code (factories, direct model
     * creates) that runs outside of an actual HTTP request/response cycle,
     * where nothing else establishes tenant Context.
     *
     * A no-op when tenancy is off, or when a tenant is already active in
     * Context and no explicit $tenantId is given (so it composes cleanly
     * inside a test that already set one up itself, e.g. via with_tenant()).
     */
    protected function withTestTenant(int|string|null $tenantId, Closure $callback): mixed
    {
        if (! has_tenancy()) {
            return $callback();
        }

        if ($tenantId === null && Context::get('tenant_id') !== null) {
            return $callback();
        }

        return with_tenant($tenantId ?? $this->resolveTenantIdForTests(), $callback);
    }

    /**
     * Tenant to act under when a test doesn't name one explicitly: the
     * configured implicit tenant in "single" mode (matching what every real
     * request resolves to — see Core\Support\Tenancy::defaultTenantId()),
     * or one throwaway tenant per test in "multi" mode, where there is no
     * "the" tenant. Memoized on $testTenantId — see its docblock. Pass
     * $tenantId to actingAsUser()/withTestTenant() directly for tests that
     * need a specific or shared tenant instead.
     */
    protected function resolveTenantIdForTests(): ?int
    {
        if ($this->testTenantId !== null) {
            return $this->testTenantId;
        }

        if (is_single_tenant()) {
            return $this->testTenantId = Tenancy::defaultTenantId();
        }

        $tenantModel = config('project.tenancy.tenant_model', Tenant::class);

        return $this->testTenantId = $tenantModel::query()->create([
            'name' => 'Test Tenant',
            'slug' => 'test-tenant-'.Str::lower(Str::random(8)),
        ])->id;
    }
}
