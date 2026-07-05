<?php

namespace Local\Permission\Tests;

use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Local\Permission\Models\Permission;
use Local\Permission\Models\Role;
use Tests\TestCase;

class HasPermissionsTest extends TestCase
{
    use RefreshDatabase;

    protected function makeUser(): User
    {
        return User::factory()->create();
    }

    public function test_direct_permission_grant_and_check(): void
    {
        $user = $this->makeUser();

        $this->assertFalse($user->hasPermissionTo('posts.create'));

        $user->givePermissionTo('posts.create');

        $this->assertTrue($user->fresh()->hasDirectPermission('posts.create'));
        $this->assertTrue($user->fresh()->hasPermissionTo('posts.create'));
    }

    public function test_permission_via_role_without_direct_grant(): void
    {
        $user = $this->makeUser();
        $role = Role::findOrCreate('editor');
        $role->givePermissionTo(Permission::findOrCreate('posts.publish'));

        $user->assignRole($role);

        $this->assertFalse($user->fresh()->hasDirectPermission('posts.publish'));
        $this->assertTrue($user->fresh()->hasPermissionTo('posts.publish'));
    }

    public function test_revoke_permission_to(): void
    {
        $user = $this->makeUser();
        $user->givePermissionTo('posts.create');

        $user->revokePermissionTo('posts.create');

        $this->assertFalse($user->fresh()->hasPermissionTo('posts.create'));
    }

    public function test_sync_permissions_replaces_the_full_set(): void
    {
        $user = $this->makeUser();
        $user->givePermissionTo('posts.create', 'posts.update');

        $user->syncPermissions(['posts.update']);

        $fresh = $user->fresh();
        $this->assertFalse($fresh->hasDirectPermission('posts.create'));
        $this->assertTrue($fresh->hasDirectPermission('posts.update'));
    }

    public function test_has_any_and_has_all_permissions(): void
    {
        $user = $this->makeUser();
        $user->givePermissionTo('posts.create');

        $this->assertTrue($user->fresh()->hasAnyPermission('posts.create', 'posts.delete'));
        $this->assertFalse($user->fresh()->hasAllPermissions('posts.create', 'posts.delete'));

        $user->givePermissionTo('posts.delete');
        $this->assertTrue($user->fresh()->hasAllPermissions('posts.create', 'posts.delete'));
    }

    public function test_get_all_permissions_combines_direct_and_role_derived(): void
    {
        $user = $this->makeUser();
        $user->givePermissionTo('posts.create');

        $role = Role::findOrCreate('editor');
        $role->givePermissionTo('posts.publish');
        $user->assignRole($role);

        $names = $user->fresh()->getAllPermissions();

        $this->assertTrue($names->contains('posts.create'));
        $this->assertTrue($names->contains('posts.publish'));
        $this->assertCount(2, $names);
    }

    public function test_permission_name_deduplicated_when_both_direct_and_via_role(): void
    {
        $user = $this->makeUser();
        $user->givePermissionTo('posts.create');

        $role = Role::findOrCreate('editor');
        $role->givePermissionTo('posts.create');
        $user->assignRole($role);

        $this->assertCount(1, $user->fresh()->getAllPermissions());
    }
}
