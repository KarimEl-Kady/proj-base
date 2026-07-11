<?php

namespace Local\Permission\Tests;

use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Local\Permission\Models\Permission;
use Local\Permission\Models\Role;
use Tests\TestCase;

class PermissionSeedCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'permission.definitions' => [
                'permissions' => ['users.view', 'users.create', 'countries.view'],
                'roles' => [
                    'admin' => ['*'],
                    'manager' => ['users.view', 'countries.view'],
                ],
            ],
            // Isolate from the real module definition files — these tests
            // assert exact counts against the config set above.
            'permission.definition_paths' => [],
        ]);
    }

    public function test_it_creates_permissions_and_roles_from_config(): void
    {
        $this->artisan('permission:seed')->assertSuccessful();

        $this->assertSame(3, Permission::query()->count());
        $this->assertSame(2, Role::query()->count());
    }

    public function test_wildcard_role_gets_every_defined_permission(): void
    {
        $this->artisan('permission:seed')->assertSuccessful();

        $admin = Role::findByName('admin');
        $this->assertTrue($admin->hasPermissionTo('users.view'));
        $this->assertTrue($admin->hasPermissionTo('users.create'));
        $this->assertTrue($admin->hasPermissionTo('countries.view'));
    }

    public function test_explicit_role_gets_only_listed_permissions(): void
    {
        $this->artisan('permission:seed')->assertSuccessful();

        $manager = Role::findByName('manager');
        $this->assertTrue($manager->hasPermissionTo('users.view'));
        $this->assertFalse($manager->hasPermissionTo('users.create'));
    }

    public function test_running_twice_is_idempotent(): void
    {
        $this->artisan('permission:seed')->assertSuccessful();
        $this->artisan('permission:seed')->assertSuccessful();

        $this->assertSame(3, Permission::query()->count());
        $this->assertSame(2, Role::query()->count());
    }

    public function test_re_seeding_after_a_config_change_updates_role_permissions(): void
    {
        $this->artisan('permission:seed')->assertSuccessful();

        config(['permission.definitions.roles.manager' => ['users.view']]);
        $this->artisan('permission:seed')->assertSuccessful();

        $this->assertFalse(Role::findByName('manager')->hasPermissionTo('countries.view'));
    }

    public function test_no_definitions_fails_gracefully(): void
    {
        config(['permission.definitions' => ['permissions' => [], 'roles' => []]]);

        $this->artisan('permission:seed')->assertFailed();
    }

    public function test_fresh_without_force_requires_confirmation(): void
    {
        $this->artisan('permission:seed')->assertSuccessful();

        $this->artisan('permission:seed', ['--fresh' => true])
            ->expectsConfirmation(
                'This deletes ALL roles/permissions and strips them from every model that has them. Continue?',
                'no'
            )
            ->assertSuccessful();

        // Aborted — nothing changed.
        $this->assertSame(3, Permission::query()->count());
    }

    public function test_fresh_with_force_wipes_existing_assignments(): void
    {
        $this->artisan('permission:seed')->assertSuccessful();

        $user = User::factory()->create();
        $user->assignRole('admin');
        $originalAdminId = Role::findByName('admin')->id;

        $this->artisan('permission:seed', ['--fresh' => true, '--force' => true])->assertSuccessful();

        // The old role row (and its pivot rows) is gone — cascaded on delete.
        $this->assertDatabaseMissing('model_has_roles', ['role_id' => $originalAdminId]);
        $this->assertDatabaseMissing('roles', ['id' => $originalAdminId]);

        // A fresh 'admin' role exists again, but the user's old assignment
        // did not survive the wipe.
        $this->assertFalse($user->fresh()->hasRole('admin'));
    }
}
