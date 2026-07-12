<?php

namespace App\Modules\User\Tests\Feature;

use App\Models\Tenant;
use App\Modules\User\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
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

    // ── tenancy awareness ────────────────────────────────────────────
    // Without this, a CLI-created admin would carry no tenant stamp and be
    // invisible to every tenant-scoped query — unable to log in.

    public function test_single_mode_stamps_the_admin_with_the_default_tenant(): void
    {
        config(['project.tenancy.mode' => 'single']);
        $this->addTenantColumnToUsers();

        $this->artisan('user:make-admin', [
            'email' => 'boss@example.com',
            '--name' => 'Boss',
            '--password' => 'super-secret-password',
        ])
            ->expectsConfirmation('No user with [boss@example.com] exists — create one?', 'yes')
            ->assertSuccessful();

        $tenant = Tenant::where('slug', 'default')->first();
        $this->assertNotNull($tenant, 'The default tenant should be created on first use.');

        $user = with_tenant($tenant->id, fn () => User::query()->where('email', 'boss@example.com')->first());
        $this->assertNotNull($user, 'The admin must be visible to queries scoped to the default tenant.');
        $this->assertSame($tenant->id, (int) $user->getAttribute('tenant_id'));
    }

    public function test_multi_mode_requires_the_tenant_option(): void
    {
        config(['project.tenancy.mode' => 'multi']);

        $this->artisan('user:make-admin', ['email' => 'boss@example.com'])
            ->expectsOutputToContain('pass --tenant=')
            ->assertFailed();
    }

    public function test_multi_mode_creates_the_admin_under_the_given_tenant(): void
    {
        config(['project.tenancy.mode' => 'multi']);
        $this->addTenantColumnToUsers();

        $tenant = Tenant::create(['name' => 'Acme', 'slug' => 'acme']);

        $this->artisan('user:make-admin', [
            'email' => 'boss@acme.com',
            '--name' => 'Acme Boss',
            '--password' => 'super-secret-password',
            '--tenant' => 'acme',
        ])
            ->expectsConfirmation('No user with [boss@acme.com] exists — create one?', 'yes')
            ->assertSuccessful();

        $user = with_tenant($tenant->id, fn () => User::query()->where('email', 'boss@acme.com')->first());
        $this->assertNotNull($user);
        $this->assertSame($tenant->id, (int) $user->getAttribute('tenant_id'));
        $this->assertTrue($user->hasRole('admin'));
    }

    public function test_multi_mode_rejects_an_unknown_tenant(): void
    {
        config(['project.tenancy.mode' => 'multi']);

        $this->artisan('user:make-admin', [
            'email' => 'boss@example.com',
            '--tenant' => 'ghost-corp',
        ])
            ->expectsOutputToContain('No active tenant matches [ghost-corp]')
            ->assertFailed();
    }

    /**
     * The test database migrates under the suite's default "none" mode,
     * where tenantColumn() is a no-op — add the column by hand.
     */
    protected function addTenantColumnToUsers(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('tenant_id')->nullable()->index();
        });
    }
}
