<?php

namespace App\Modules\Auth\Tests\Feature;

use App\Modules\Auth\Listeners\AssignDefaultRole;
use App\Modules\User\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Local\Permission\Models\Role;
use Tests\TestCase;

class AssignDefaultRoleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (! in_array('Auth', config('project.modules'))) {
            $this->markTestSkipped('Module [Auth] is disabled.');
        }

        config(['project.auth.driver' => 'sanctum']);
    }

    protected function register(): User
    {
        $this->postJson('/api/v1/auth/register', [
            'name' => 'Alice',
            'email' => 'alice@example.com',
            'password' => 'secret-password',
        ])->assertCreated();

        return User::query()->where('email', 'alice@example.com')->firstOrFail();
    }

    public function test_listener_is_auto_discovered_from_the_module_listeners_directory(): void
    {
        $raw = collect(app('events')->getRawListeners()[Registered::class] ?? [])
            ->map(fn ($listener) => is_string($listener) ? $listener : (is_array($listener) ? implode('@', $listener) : get_debug_type($listener)));

        $this->assertTrue(
            $raw->contains(fn (string $listener) => str_contains($listener, AssignDefaultRole::class)),
            'AssignDefaultRole was not discovered for the Registered event. Raw listeners: '.$raw->implode(', ')
        );
    }

    public function test_registered_user_gets_the_configured_default_role(): void
    {
        config(['project.auth.default_role' => 'customer']);

        $user = $this->register();

        $this->assertTrue($user->hasRole('customer'));
        // assignRole's findOrCreate created the role on the fly
        $this->assertSame(1, Role::query()->where('name', 'customer')->count());
    }

    public function test_no_role_is_assigned_when_default_role_is_unset(): void
    {
        config(['project.auth.default_role' => null]);

        $user = $this->register();

        $this->assertSame(0, $user->roles()->count());
        $this->assertSame(0, Role::query()->count());
    }

    public function test_listener_reuses_an_existing_role_instead_of_duplicating(): void
    {
        config(['project.auth.default_role' => 'customer']);
        $existing = Role::findOrCreate('customer');

        $user = $this->register();

        $this->assertTrue($user->roles->contains('id', $existing->id));
        $this->assertSame(1, Role::query()->where('name', 'customer')->count());
    }
}
