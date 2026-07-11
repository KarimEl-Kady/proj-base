<?php

namespace App\Modules\User\Tests\Feature;

use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MakeAdminCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_promotes_an_existing_user_and_seeds_definitions_automatically(): void
    {
        $user = User::factory()->create(['email' => 'boss@example.com']);

        // No permission:seed has run — the command must bootstrap it itself.
        $this->artisan('user:make-admin', ['email' => 'boss@example.com'])
            ->expectsOutputToContain('Granted [admin] to [boss@example.com].')
            ->assertSuccessful();

        $user->refresh();
        $this->assertTrue($user->hasRole('admin'));

        // Admin's '*' wildcard resolved against the full merged definitions,
        // including module-owned ones.
        $this->assertTrue($user->hasPermissionTo('users.delete'));
        $this->assertTrue($user->hasPermissionTo('cities.manage'));
    }

    public function test_creates_the_user_when_the_email_is_unknown(): void
    {
        $this->artisan('user:make-admin', [
            'email' => 'new-admin@example.com',
            '--name' => 'New Admin',
            '--password' => 'super-secret-password',
        ])
            ->expectsConfirmation('No user with [new-admin@example.com] exists — create one?', 'yes')
            ->assertSuccessful();

        $user = User::query()->where('email', 'new-admin@example.com')->first();

        $this->assertNotNull($user);
        $this->assertSame('New Admin', $user->name);
        $this->assertTrue(Hash::check('super-secret-password', $user->password));
        $this->assertTrue($user->hasRole('admin'));
    }

    public function test_declining_the_create_prompt_aborts_without_side_effects(): void
    {
        $this->artisan('user:make-admin', ['email' => 'ghost@example.com'])
            ->expectsConfirmation('No user with [ghost@example.com] exists — create one?', 'no')
            ->expectsOutputToContain('Aborted.')
            ->assertSuccessful();

        $this->assertDatabaseMissing('users', ['email' => 'ghost@example.com']);
    }

    public function test_rerunning_against_an_existing_admin_is_idempotent(): void
    {
        User::factory()->create(['email' => 'boss@example.com']);

        $this->artisan('user:make-admin', ['email' => 'boss@example.com'])->assertSuccessful();

        $this->artisan('user:make-admin', ['email' => 'boss@example.com'])
            ->expectsOutputToContain('already has the [admin] role')
            ->assertSuccessful();
    }

    public function test_fails_with_guidance_when_the_role_is_not_defined(): void
    {
        User::factory()->create(['email' => 'boss@example.com']);

        $this->artisan('user:make-admin', [
            'email' => 'boss@example.com',
            '--role' => 'superhero',
        ])
            ->expectsOutputToContain('Role [superhero] is not defined')
            ->assertFailed();
    }
}
