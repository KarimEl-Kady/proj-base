<?php

namespace Local\Permission\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Local\Permission\Models\Permission;
use Local\Permission\Models\Role;

class PermissionListCommand extends Command
{
    protected $signature = 'permission:list';

    protected $description = 'List all roles and permissions currently in the database';

    public function handle(): int
    {
        $tables = config('permission.table_names');

        $roleRows = Role::query()->get()->map(function (Role $role) use ($tables) {
            $assigned = DB::table($tables['model_has_roles'])->where('role_id', $role->id)->count();

            return [$role->name, $role->guard_name ?? '—', $role->permissions()->count(), $assigned];
        });

        $this->info('Roles:');
        if ($roleRows->isEmpty()) {
            $this->line('  none — run: php artisan permission:seed');
        } else {
            $this->table(['Name', 'Guard', 'Permissions', 'Assigned to'], $roleRows->all());
        }

        $permissionRows = Permission::query()->get()->map(function (Permission $permission) use ($tables) {
            $direct = DB::table($tables['model_has_permissions'])->where('permission_id', $permission->id)->count();

            return [$permission->name, $permission->guard_name ?? '—', $permission->roles()->count(), $direct];
        });

        $this->newLine();
        $this->info('Permissions:');
        if ($permissionRows->isEmpty()) {
            $this->line('  none — run: php artisan permission:seed');
        } else {
            $this->table(['Name', 'Guard', 'Via Roles', 'Direct Grants'], $permissionRows->all());
        }

        return self::SUCCESS;
    }
}
