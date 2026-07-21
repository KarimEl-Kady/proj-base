<?php

namespace Local\Permission\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Local\Permission\Models\Permission;
use Local\Permission\Models\Role;
use Tests\TestCase;

class RoleModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_find_or_create_is_idempotent(): void
    {
        $first = Role::findOrCreate('admin');
        $second = Role::findOrCreate('admin');

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, Role::query()->count());
    }

    public function test_find_by_name_throws_when_missing(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Role::findByName('does-not-exist');
    }

    public function test_give_revoke_and_sync_permissions(): void
    {
        $role = Role::findOrCreate('editor');
        Permission::findOrCreate('posts.create');
        Permission::findOrCreate('posts.update');
        Permission::findOrCreate('posts.delete');

        $role->givePermissionTo('posts.create', 'posts.update');
        $this->assertTrue($role->fresh()->hasPermissionTo('posts.create'));
        $this->assertTrue($role->fresh()->hasPermissionTo('posts.update'));

        $role->revokePermissionTo('posts.update');
        $this->assertFalse($role->fresh()->hasPermissionTo('posts.update'));

        $role->syncPermissions(['posts.delete']);
        $fresh = $role->fresh();
        $this->assertFalse($fresh->hasPermissionTo('posts.create'));
        $this->assertTrue($fresh->hasPermissionTo('posts.delete'));
    }

    public function test_give_permission_to_accepts_permission_instances(): void
    {
        $role = Role::findOrCreate('editor');
        $permission = Permission::findOrCreate('posts.publish');

        $role->givePermissionTo($permission);

        $this->assertTrue($role->fresh()->hasPermissionTo($permission));
    }

    public function test_deleting_a_role_cascades_its_pivot_rows(): void
    {
        $role = Role::findOrCreate('temp-role');
        $role->givePermissionTo(Permission::findOrCreate('temp.permission'));

        $roleId = $role->id;
        $role->delete();

        $this->assertDatabaseMissing('role_has_permissions', ['role_id' => $roleId]);
    }

    public function test_find_or_create_defaults_to_a_global_role(): void
    {
        $role = Role::findOrCreate('unscoped');

        $this->assertNull($role->tenant_id);
    }

    public function test_find_or_create_for_tenant_scopes_a_role_to_one_tenant(): void
    {
        $role = Role::findOrCreateForTenant(7, 'reviewer');

        $this->assertSame(7, $role->tenant_id);
        $this->assertSame(1, Role::query()->where('name', 'reviewer')->count());
    }

    public function test_find_or_create_for_tenant_is_idempotent_per_tenant(): void
    {
        $first = Role::findOrCreateForTenant(7, 'reviewer');
        $second = Role::findOrCreateForTenant(7, 'reviewer');

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, Role::query()->where('name', 'reviewer')->count());
    }

    public function test_two_different_tenants_can_each_own_a_role_with_the_same_name(): void
    {
        $tenantA = Role::findOrCreateForTenant(1, 'admin');
        $tenantB = Role::findOrCreateForTenant(2, 'admin');

        $this->assertNotSame($tenantA->id, $tenantB->id);
        $this->assertSame(1, $tenantA->tenant_id);
        $this->assertSame(2, $tenantB->tenant_id);
        $this->assertSame(2, Role::query()->where('name', 'admin')->count());
    }

    public function test_a_tenant_scoped_role_does_not_collide_with_a_same_named_global_role(): void
    {
        $global = Role::findOrCreate('admin');
        $tenantScoped = Role::findOrCreateForTenant(1, 'admin');

        $this->assertNotSame($global->id, $tenantScoped->id);
        $this->assertNull($global->tenant_id);
        $this->assertSame(1, $tenantScoped->tenant_id);
    }

    public function test_global_find_by_name_never_resolves_a_tenant_scoped_role(): void
    {
        Role::findOrCreateForTenant(1, 'only-scoped');

        $this->expectException(InvalidArgumentException::class);

        Role::findByName('only-scoped');
    }

    public function test_find_by_name_for_tenant_resolves_the_correct_tenants_role(): void
    {
        $tenantA = Role::findOrCreateForTenant(1, 'manager');
        Role::findOrCreateForTenant(2, 'manager');

        $found = Role::findByNameForTenant(1, 'manager');

        $this->assertSame($tenantA->id, $found->id);
    }

    public function test_find_by_name_for_tenant_throws_when_missing_for_that_tenant(): void
    {
        Role::findOrCreateForTenant(2, 'manager');

        $this->expectException(InvalidArgumentException::class);

        Role::findByNameForTenant(1, 'manager');
    }

    public function test_find_by_name_for_tenant_with_null_resolves_the_global_role(): void
    {
        $global = Role::findOrCreate('super-admin');

        $found = Role::findByNameForTenant(null, 'super-admin');

        $this->assertSame($global->id, $found->id);
    }
}
