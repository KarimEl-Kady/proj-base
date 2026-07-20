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

    public function test_tenant_id_defaults_to_null_and_is_a_bare_seam_not_a_feature(): void
    {
        $role = Role::findOrCreate('unscoped');

        $this->assertNull($role->tenant_id);

        $role->update(['tenant_id' => 42]);

        $this->assertSame(42, $role->fresh()->tenant_id);
    }
}
