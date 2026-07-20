<?php

namespace App\Modules\User\Tests\Feature;

use App\Models\Tenant;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tests\TestCase;

/**
 * HTTP-layer proof that the /api/v1/users CRUD surface — a real,
 * permission-gated module, not a synthetic probe — actually enforces
 * tenant isolation end to end: HasTenantScope filtering on index/show, and
 * a 404 (not a leaked 200/403) for another tenant's record by uuid.
 * MultiTenantIdentityTest covers the same guarantee for Auth
 * (registration, password reset); this is its User-module counterpart.
 */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
class UserTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    public static function setUpBeforeClass(): void
    {
        putenv('PROJECT_TENANCY_MODE=multi');
        putenv('PROJECT_TENANT_IDENTIFICATION=header');
    }

    public static function tearDownAfterClass(): void
    {
        putenv('PROJECT_TENANCY_MODE');
        putenv('PROJECT_TENANT_IDENTIFICATION');
    }

    protected function setUp(): void
    {
        parent::setUp();

        if (! in_array('User', config('project.modules'))) {
            $this->markTestSkipped('Module [User] is disabled.');
        }
    }

    protected function actingAsAdminFor(Tenant $tenant): User
    {
        $admin = with_tenant($tenant->id, fn () => User::factory()->create());
        $admin->givePermissionTo(['users.view', 'users.create', 'users.update', 'users.delete']);
        Sanctum::actingAs($admin);

        return $admin;
    }

    public function test_index_only_lists_the_current_tenants_users(): void
    {
        $acme = Tenant::create(['name' => 'Acme', 'slug' => 'acme']);
        $globex = Tenant::create(['name' => 'Globex', 'slug' => 'globex']);

        $acmeAdmin = $this->actingAsAdminFor($acme);
        with_tenant($globex->id, fn () => User::factory()->count(2)->create());

        $response = $this->getJson('/api/v1/users', ['X-Tenant-ID' => 'acme']);

        $response->assertOk();
        // Just the admin itself — Globex's two users are invisible.
        $this->assertCount(1, $response->json('data.data'));
        $this->assertSame($acmeAdmin->uuid, $response->json('data.data.0.id'));
    }

    public function test_show_returns_404_for_another_tenants_uuid_instead_of_leaking_it(): void
    {
        $acme = Tenant::create(['name' => 'Acme', 'slug' => 'acme']);
        $globex = Tenant::create(['name' => 'Globex', 'slug' => 'globex']);

        $this->actingAsAdminFor($acme);
        $globexUser = with_tenant($globex->id, fn () => User::factory()->create());

        // Not 403 (which would confirm the record exists) — 404, same as
        // an unknown uuid, so tenant B's data can't be probed for from A.
        $this->getJson("/api/v1/users/{$globexUser->uuid}", ['X-Tenant-ID' => 'acme'])
            ->assertNotFound();
    }

    public function test_update_and_destroy_cannot_reach_another_tenants_record(): void
    {
        $acme = Tenant::create(['name' => 'Acme', 'slug' => 'acme']);
        $globex = Tenant::create(['name' => 'Globex', 'slug' => 'globex']);

        $this->actingAsAdminFor($acme);
        $globexUser = with_tenant($globex->id, fn () => User::factory()->create());

        $this->putJson("/api/v1/users/{$globexUser->uuid}", ['name' => 'Hijacked'], ['X-Tenant-ID' => 'acme'])
            ->assertNotFound();
        $this->deleteJson("/api/v1/users/{$globexUser->uuid}", [], ['X-Tenant-ID' => 'acme'])
            ->assertNotFound();

        with_tenant($globex->id, function () use ($globexUser): void {
            $this->assertNotSame('Hijacked', $globexUser->fresh()->name);
            $this->assertNotNull(User::query()->find($globexUser->id));
        });
    }

    public function test_two_tenants_can_use_the_same_email_independently(): void
    {
        $acme = Tenant::create(['name' => 'Acme', 'slug' => 'acme']);
        $globex = Tenant::create(['name' => 'Globex', 'slug' => 'globex']);
        $email = 'shared@example.com';

        $this->actingAsAdminFor($acme);
        $this->postJson('/api/v1/users', [
            'name' => 'Acme Owner',
            'email' => $email,
            'password' => 'secret123',
        ], ['X-Tenant-ID' => 'acme'])->assertCreated();

        $this->actingAsAdminFor($globex);
        $this->postJson('/api/v1/users', [
            'name' => 'Globex Owner',
            'email' => $email,
            'password' => 'secret123',
        ], ['X-Tenant-ID' => 'globex'])->assertCreated();

        $this->assertSame(2, User::query()->withoutGlobalScopes()->where('email', $email)->count());
    }
}
