<?php

namespace Local\Permission\Tests;

use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Local\Permission\Models\Role;
use Tests\TestCase;

class MiddlewareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware('api')->group(function () {
            Route::get('/__test/role', fn () => response()->json(['ok' => true]))
                ->middleware('role:admin|manager');

            Route::get('/__test/permission', fn () => response()->json(['ok' => true]))
                ->middleware('permission:posts.create');

            Route::get('/__test/role-or-permission', fn () => response()->json(['ok' => true]))
                ->middleware('role_or_permission:admin|posts.create');
        });
    }

    public function test_role_middleware_blocks_users_without_any_listed_role(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/__test/role')
            ->assertForbidden();
    }

    public function test_role_middleware_allows_users_with_any_listed_role(): void
    {
        $user = User::factory()->create();
        $user->assignRole('manager');

        $this->actingAs($user, 'sanctum')
            ->getJson('/__test/role')
            ->assertOk();
    }

    public function test_role_middleware_blocks_guests(): void
    {
        $this->getJson('/__test/role')->assertForbidden();
    }

    public function test_permission_middleware_blocks_without_the_permission(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/__test/permission')
            ->assertForbidden();
    }

    public function test_permission_middleware_allows_direct_grant(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('posts.create');

        $this->actingAs($user, 'sanctum')
            ->getJson('/__test/permission')
            ->assertOk();
    }

    public function test_permission_middleware_allows_permission_granted_via_role(): void
    {
        $user = User::factory()->create();
        $role = Role::findOrCreate('editor');
        $role->givePermissionTo('posts.create');
        $user->assignRole($role);

        $this->actingAs($user, 'sanctum')
            ->getJson('/__test/permission')
            ->assertOk();
    }

    public function test_role_or_permission_middleware_passes_on_either(): void
    {
        $byRole = User::factory()->create();
        $byRole->assignRole('admin');

        $this->actingAs($byRole, 'sanctum')->getJson('/__test/role-or-permission')->assertOk();

        $byPermission = User::factory()->create();
        $byPermission->givePermissionTo('posts.create');

        $this->actingAs($byPermission, 'sanctum')->getJson('/__test/role-or-permission')->assertOk();
    }

    public function test_forbidden_response_uses_the_project_envelope(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->getJson('/__test/role');

        $response->assertForbidden();
        $response->assertJsonPath('success', false);
        $response->assertJsonPath('message', 'User does not have any of the necessary roles: admin, manager');
    }
}
