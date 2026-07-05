<?php

namespace Local\Permission\Tests;

use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Local\Permission\Models\Permission;
use Local\Permission\Models\Role;
use Tests\TestCase;

class PermissionListCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_runs_successfully_with_nothing_seeded_yet(): void
    {
        $this->artisan('permission:list')
            ->expectsOutputToContain('none — run: php artisan permission:seed')
            ->assertSuccessful();
    }

    public function test_it_reports_roles_and_permissions_with_counts(): void
    {
        $role = Role::findOrCreate('admin');
        $role->givePermissionTo(Permission::findOrCreate('users.view'));

        $user = User::factory()->create();
        $user->assignRole($role);

        $this->artisan('permission:list')->assertSuccessful();
    }
}
