<?php

namespace App\Modules\Core\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Local\Permission\Models\Role;
use Tests\TestCase;

/**
 * Proves the module-owned permission story is wired end to end with the
 * real, unmodified config: each shipped module's Config/permissions.php is
 * discovered through permission.definition_paths and lands in the database
 * on permission:seed.
 */
class ModulePermissionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_seed_discovers_every_shipped_module_definition_file(): void
    {
        $this->artisan('permission:seed')->assertSuccessful();

        foreach ([
            'users.view', 'users.create', 'users.update', 'users.delete', // User module
            'countries.view', 'countries.manage',                         // Country module
            'cities.view', 'cities.manage',                               // City module
        ] as $permission) {
            $this->assertDatabaseHas('permissions', ['name' => $permission]);
        }
    }

    public function test_admin_wildcard_covers_module_declared_permissions(): void
    {
        $this->artisan('permission:seed')->assertSuccessful();

        $admin = Role::findByName('admin');

        $this->assertTrue($admin->hasPermissionTo('users.delete'));
        $this->assertTrue($admin->hasPermissionTo('cities.manage'));
    }
}
