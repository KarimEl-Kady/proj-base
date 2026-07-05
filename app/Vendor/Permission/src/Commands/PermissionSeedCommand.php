<?php

namespace Local\Permission\Commands;

use Illuminate\Console\Command;
use Local\Permission\Models\Permission;
use Local\Permission\Models\Role;

/**
 * Syncs the database to config('permission.definitions') — the "detect
 * easily what will be seeded" entry point, mirroring geo:seed: print the
 * plan, then execute it. Safe to re-run (findOrCreate + full sync).
 */
class PermissionSeedCommand extends Command
{
    protected $signature = 'permission:seed
                            {--fresh : Delete ALL existing roles/permissions first — also strips every model\'s role/permission assignments}
                            {--force : Skip the confirmation prompt for --fresh}';

    protected $description = 'Sync roles and permissions from config(permission.definitions)';

    public function handle(): int
    {
        $permissionNames = config('permission.definitions.permissions', []);
        $roleDefinitions = config('permission.definitions.roles', []);

        if ($permissionNames === [] && $roleDefinitions === []) {
            $this->error('No definitions found — set them in config/permission.php under "definitions".');

            return self::FAILURE;
        }

        $this->reportPlan($permissionNames, $roleDefinitions);

        if ($this->option('fresh') && ! $this->confirmFresh()) {
            $this->info('Aborted.');

            return self::SUCCESS;
        }

        if ($this->option('fresh')) {
            Role::query()->delete();
            Permission::query()->delete();
            $this->line('Existing roles and permissions removed.');
        }

        foreach ($permissionNames as $name) {
            Permission::findOrCreate($name);
        }

        foreach ($roleDefinitions as $roleName => $rolePermissions) {
            $resolved = in_array('*', $rolePermissions, true) ? $permissionNames : $rolePermissions;

            Role::findOrCreate($roleName)->syncPermissions($resolved);
        }

        $this->reportResult();

        return self::SUCCESS;
    }

    /**
     * @param  array<int, string>  $permissionNames
     * @param  array<string, array<int, string>>  $roleDefinitions
     */
    protected function reportPlan(array $permissionNames, array $roleDefinitions): void
    {
        $existingPermissions = Permission::query()->pluck('name')->all();

        $permissionRows = collect($permissionNames)->map(fn (string $name) => [
            $name,
            in_array($name, $existingPermissions, true) ? 'exists' : '<fg=green>new</>',
        ]);

        $this->info('Permissions:');
        $this->table(['Name', 'Status'], $permissionRows->all());

        $existingRoles = Role::query()->pluck('name')->all();

        $roleRows = collect($roleDefinitions)->map(function (array $permissions, string $roleName) use ($permissionNames, $existingRoles) {
            $count = in_array('*', $permissions, true) ? count($permissionNames) : count($permissions);

            return [
                $roleName,
                $count,
                in_array($roleName, $existingRoles, true) ? 'exists' : '<fg=green>new</>',
            ];
        });

        $this->info('Roles:');
        $this->table(['Name', 'Permissions', 'Status'], $roleRows->values()->all());
    }

    protected function confirmFresh(): bool
    {
        if ($this->option('force')) {
            return true;
        }

        return $this->confirm(
            'This deletes ALL roles/permissions and strips them from every model that has them. Continue?',
            default: false
        );
    }

    protected function reportResult(): void
    {
        $this->newLine();
        $this->info(sprintf(
            'Done — %d permissions, %d roles synced.',
            Permission::query()->count(),
            Role::query()->count()
        ));
    }
}
