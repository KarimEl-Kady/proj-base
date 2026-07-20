<?php

namespace App\Modules\User\Tests\Feature;

use App\Models\Tenant;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tests\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
class MultiTenantIdentityTest extends TestCase
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

    public function test_each_tenant_can_register_the_same_email(): void
    {
        config(['project.tenancy.registration' => 'open']);
        Tenant::query()->create(['name' => 'Acme', 'slug' => 'acme']);
        Tenant::query()->create(['name' => 'Globex', 'slug' => 'globex']);
        $payload = [
            'name' => 'Owner',
            'email' => 'owner@example.com',
            'password' => 'secret-password',
        ];

        $this->postJson('/api/v1/auth/register', $payload, ['X-Tenant-ID' => 'acme'])
            ->assertCreated();
        $this->postJson('/api/v1/auth/register', $payload, ['X-Tenant-ID' => 'globex'])
            ->assertCreated();

        $this->assertDatabaseCount('users', 2);
    }

    public function test_multi_tenant_self_registration_is_fail_closed(): void
    {
        Tenant::query()->create(['name' => 'Acme', 'slug' => 'acme']);

        $this->postJson('/api/v1/auth/register', [
            'name' => 'Owner',
            'email' => 'owner@example.com',
            'password' => 'secret-password',
        ], ['X-Tenant-ID' => 'acme'])->assertForbidden();

        $this->assertDatabaseCount('users', 0);
    }

    public function test_password_reset_token_cannot_cross_tenant_boundaries(): void
    {
        $tenantA = Tenant::query()->create(['name' => 'Acme', 'slug' => 'acme']);
        $tenantB = Tenant::query()->create(['name' => 'Globex', 'slug' => 'globex']);
        $userA = with_tenant($tenantA->id, fn () => User::query()->create([
            'name' => 'Owner A',
            'email' => 'owner@example.com',
            'password' => 'old-password-a',
        ]));
        $userB = with_tenant($tenantB->id, fn () => User::query()->create([
            'name' => 'Owner B',
            'email' => 'owner@example.com',
            'password' => 'old-password-b',
        ]));
        $tokenForB = with_tenant(
            $tenantB->id,
            fn () => Password::createToken($userB),
        );

        $this->postJson('/api/v1/auth/password/reset', [
            'token' => $tokenForB,
            'email' => 'owner@example.com',
            'password' => 'new-password-a',
            'password_confirmation' => 'new-password-a',
        ], ['X-Tenant-ID' => 'acme'])->assertUnprocessable();

        $this->assertTrue(Hash::check('old-password-a', $userA->fresh()->password));

        $this->postJson('/api/v1/auth/password/reset', [
            'token' => $tokenForB,
            'email' => 'owner@example.com',
            'password' => 'new-password-b',
            'password_confirmation' => 'new-password-b',
        ], ['X-Tenant-ID' => 'globex'])->assertOk();

        $this->assertTrue(Hash::check('new-password-b', $userB->fresh()->password));
    }
}
