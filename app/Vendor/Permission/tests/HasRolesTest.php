<?php

namespace Local\Permission\Tests;

use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Local\Permission\Models\Role;
use Tests\TestCase;

/**
 * Exercises HasRoles against the app's real User model — proof the trait
 * (wired in App\Modules\User\Models\User) works end-to-end, not just in
 * isolation.
 */
class HasRolesTest extends TestCase
{
    use RefreshDatabase;

    protected function makeUser(): User
    {
        return User::factory()->create();
    }

    public function test_assign_and_has_role(): void
    {
        $user = $this->makeUser();

        $this->assertFalse($user->hasRole('admin'));

        $user->assignRole('admin');

        $this->assertTrue($user->fresh()->hasRole('admin'));
        $this->assertTrue($user->fresh()->hasRole(Role::findByName('admin')));
    }

    public function test_assign_role_is_idempotent(): void
    {
        $user = $this->makeUser();

        $user->assignRole('admin');
        $user->assignRole('admin');

        $this->assertSame(1, $user->fresh()->roles()->count());
    }

    public function test_assign_multiple_roles_at_once(): void
    {
        $user = $this->makeUser();

        $user->assignRole('admin', 'manager');

        $fresh = $user->fresh();
        $this->assertTrue($fresh->hasRole('admin'));
        $this->assertTrue($fresh->hasRole('manager'));
    }

    public function test_remove_role(): void
    {
        $user = $this->makeUser();
        $user->assignRole('admin');

        $user->removeRole('admin');

        $this->assertFalse($user->fresh()->hasRole('admin'));
    }

    public function test_sync_roles_replaces_the_full_set(): void
    {
        $user = $this->makeUser();
        $user->assignRole('admin', 'manager');

        $user->syncRoles('manager');

        $fresh = $user->fresh();
        $this->assertFalse($fresh->hasRole('admin'));
        $this->assertTrue($fresh->hasRole('manager'));
    }

    public function test_has_any_role_and_has_all_roles(): void
    {
        $user = $this->makeUser();
        $user->assignRole('manager');

        $this->assertTrue($user->fresh()->hasAnyRole('admin', 'manager'));
        $this->assertFalse($user->fresh()->hasAllRoles('admin', 'manager'));

        $user->assignRole('admin');
        $this->assertTrue($user->fresh()->hasAllRoles('admin', 'manager'));
    }

    public function test_assign_role_by_name_creates_it_if_missing(): void
    {
        $user = $this->makeUser();

        $this->assertSame(0, Role::query()->where('name', 'brand-new-role')->count());

        $user->assignRole('brand-new-role');

        $this->assertSame(1, Role::query()->where('name', 'brand-new-role')->count());
    }

    public function test_assign_role_for_tenant_resolves_that_tenants_role_not_the_global_one(): void
    {
        $userA = $this->makeUser();
        $userB = $this->makeUser();

        $userA->assignRoleForTenant(1, 'admin');
        $userB->assignRoleForTenant(2, 'admin');

        $roleA = Role::findByNameForTenant(1, 'admin');
        $roleB = Role::findByNameForTenant(2, 'admin');

        $this->assertNotSame($roleA->id, $roleB->id);
        $this->assertTrue($userA->fresh()->roles->contains('id', $roleA->id));
        $this->assertTrue($userB->fresh()->roles->contains('id', $roleB->id));
        $this->assertFalse($userA->fresh()->roles->contains('id', $roleB->id));
    }

    public function test_assign_role_for_tenant_accepts_a_role_instance_directly(): void
    {
        $user = $this->makeUser();
        $role = Role::findOrCreateForTenant(5, 'inspector');

        $user->assignRoleForTenant(5, $role);

        $this->assertTrue($user->fresh()->hasRole($role));
    }
}
