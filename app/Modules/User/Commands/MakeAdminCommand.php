<?php

namespace App\Modules\User\Commands;

use App\Modules\Core\Support\Tenancy;
use App\Modules\User\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Local\Permission\Models\Role;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

/**
 * One-command bootstrap for a fresh install: syncs the declarative
 * roles/permissions if the target role isn't seeded yet, creates the user
 * if the email is unknown (interactive or via --name/--password), and
 * grants the role. Idempotent — re-running against an existing admin just
 * reports the current state.
 *
 * Tenancy-aware: User is tenant-scoped, so in single mode the command runs
 * under the implicit default tenant, and in multi mode it requires
 * --tenant= (slug or subdomain) — otherwise the created admin would carry
 * no tenant stamp and be invisible to every scoped query (unable to log in).
 */
class MakeAdminCommand extends Command
{
    protected $signature = 'user:make-admin
                            {email? : Email of the user to promote (prompted if omitted)}
                            {--name= : Name for the user when one has to be created}
                            {--password= : Password for the user when one has to be created}
                            {--role=admin : Role to grant (must exist in the permission definitions)}
                            {--tenant= : Tenant slug/subdomain to act under (required in multi mode)}';

    protected $description = 'Grant a user the admin role, seeding definitions and creating the user if needed';

    public function handle(): int
    {
        $role = (string) $this->option('role');

        if (! $this->ensureRoleIsSeeded($role)) {
            return self::FAILURE;
        }

        $tenantId = null;

        if (has_tenancy() && ($tenantId = $this->resolveTenantId()) === null) {
            return self::FAILURE;
        }

        return has_tenancy()
            ? with_tenant($tenantId, fn (): int => $this->promote($role))
            : $this->promote($role);
    }

    protected function promote(string $role): int
    {
        $email = $this->argument('email') ?? text(
            label: 'Email of the admin user',
            required: true,
            validate: fn (string $value) => filter_var($value, FILTER_VALIDATE_EMAIL) !== false
                ? null
                : 'Enter a valid email address.',
        );
        $email = mb_strtolower(trim((string) $email));

        $user = User::query()->where('email', $email)->first()
            ?? $this->createUser($email);

        if ($user === null) {
            $this->info('Aborted.');

            return self::SUCCESS;
        }

        if ($user->hasRole($role)) {
            $this->info("[{$email}] already has the [{$role}] role — nothing to do.");
        } else {
            $user->assignRole($role);
            $this->info("Granted [{$role}] to [{$email}].");
        }

        $this->line(sprintf(
            'The user now holds %d permission(s) in total.',
            $user->getAllPermissions()->count()
        ));

        return self::SUCCESS;
    }

    /**
     * Which tenant to act under when tenancy is active. Single mode: the
     * implicit default tenant. Multi mode: --tenant= is mandatory — there
     * is no "no tenant" to create an admin in.
     */
    protected function resolveTenantId(): ?int
    {
        if (is_single_tenant()) {
            $tenantId = Tenancy::defaultTenantId();

            if ($tenantId === null) {
                $this->error('The default tenant is deactivated — reactivate it before bootstrapping an admin.');
            }

            return $tenantId;
        }

        $identifier = (string) $this->option('tenant');

        if ($identifier === '') {
            $this->error('Multi-tenant mode: pass --tenant={slug or subdomain} to say which tenant this admin belongs to.');

            return null;
        }

        $tenantId = Tenancy::lookupTenantId($identifier);

        if ($tenantId === null) {
            $this->error("No active tenant matches [{$identifier}].");
        }

        return $tenantId;
    }

    /**
     * The role must come from the declarative definitions (central config +
     * module Config/permissions.php files) so it actually carries
     * permissions — a bare Role::findOrCreate here would grant a role that
     * allows nothing.
     */
    protected function ensureRoleIsSeeded(string $role): bool
    {
        if ($this->roleExists($role)) {
            return true;
        }

        $this->line('Role not seeded yet — syncing roles/permissions from definitions…');
        $this->callSilently('permission:seed');

        if ($this->roleExists($role)) {
            return true;
        }

        $this->error(
            "Role [{$role}] is not defined — add it under \"definitions\" in config/permission.php ".
            '(or a module\'s Config/permissions.php), then re-run.'
        );

        return false;
    }

    /**
     * Hits the database each call — permission:seed runs between the two
     * checks in ensureRoleIsSeeded() and is expected to change the answer.
     *
     * @phpstan-impure
     */
    protected function roleExists(string $role): bool
    {
        return Role::query()->where('name', $role)->exists();
    }

    protected function createUser(string $email): ?User
    {
        if (! confirm("No user with [{$email}] exists — create one?", default: true)) {
            return null;
        }

        $name = $this->option('name') ?? text(
            label: 'Name',
            default: Str::headline(Str::before($email, '@')),
            required: true,
        );

        $password = $this->option('password') ?? password(
            label: 'Password',
            required: true,
            validate: fn (string $value) => $this->passwordError($value),
        );

        if (($error = $this->passwordError((string) $password)) !== null) {
            $this->error($error);

            return null;
        }

        $user = User::query()->create([
            'name' => $name,
            'email' => $email,
            'password' => $password,
        ]);

        $this->info("Created user [{$email}].");

        return $user;
    }

    /**
     * Same policy as registration and CreateUserRequest (Password::defaults())
     * — the bootstrap admin account, of all accounts, should not get a
     * weaker bar than a public signup form. Checked here too (not just in
     * the prompt's validate: callback) so a scripted --password= can't
     * skip it.
     */
    protected function passwordError(string $value): ?string
    {
        $validator = Validator::make(
            ['password' => $value],
            ['password' => ['required', 'string', Password::defaults()]],
        );

        return $validator->fails() ? $validator->errors()->first('password') : null;
    }
}
