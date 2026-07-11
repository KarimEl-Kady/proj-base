<?php

namespace App\Modules\User\Commands;

use App\Modules\User\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
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
 */
class MakeAdminCommand extends Command
{
    protected $signature = 'user:make-admin
                            {email? : Email of the user to promote (prompted if omitted)}
                            {--name= : Name for the user when one has to be created}
                            {--password= : Password for the user when one has to be created}
                            {--role=admin : Role to grant (must exist in the permission definitions)}';

    protected $description = 'Grant a user the admin role, seeding definitions and creating the user if needed';

    public function handle(): int
    {
        $role = (string) $this->option('role');

        if (! $this->ensureRoleIsSeeded($role)) {
            return self::FAILURE;
        }

        $email = $this->argument('email') ?? text(
            label: 'Email of the admin user',
            required: true,
            validate: fn (string $value) => filter_var($value, FILTER_VALIDATE_EMAIL) !== false
                ? null
                : 'Enter a valid email address.',
        );

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
     * The role must come from the declarative definitions (central config +
     * module Config/permissions.php files) so it actually carries
     * permissions — a bare Role::findOrCreate here would grant a role that
     * allows nothing.
     */
    protected function ensureRoleIsSeeded(string $role): bool
    {
        if (Role::query()->where('name', $role)->exists()) {
            return true;
        }

        $this->line('Role not seeded yet — syncing roles/permissions from definitions…');
        $this->callSilently('permission:seed');

        if (Role::query()->where('name', $role)->exists()) {
            return true;
        }

        $this->error(
            "Role [{$role}] is not defined — add it under \"definitions\" in config/permission.php ".
            '(or a module\'s Config/permissions.php), then re-run.'
        );

        return false;
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
            validate: fn (string $value) => strlen($value) >= 8
                ? null
                : 'The password must be at least 8 characters.',
        );

        $user = User::query()->create([
            'name' => $name,
            'email' => $email,
            'password' => $password,
        ]);

        $this->info("Created user [{$email}].");

        return $user;
    }
}
