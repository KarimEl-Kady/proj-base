<?php

namespace Local\Permission\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Caches the whole role -> permission-name map as one small payload,
 * since it's read on every permission check and rarely changes. Anything
 * that writes to roles/permissions/role_has_permissions (Role's model
 * events, see Models/Role.php) flushes it automatically.
 */
class PermissionRegistry
{
    /**
     * @return Collection<int, string> permission names for one role id
     */
    public function permissionNamesForRole(int $roleId): Collection
    {
        return $this->map()->get($roleId, collect());
    }

    /**
     * @return Collection<int, Collection<int, string>> role id => permission names
     */
    protected function map(): Collection
    {
        $plain = config('permission.cache.enabled', true)
            ? Cache::remember(
                config('permission.cache.key', 'local.permission.role_permission_map'),
                config('permission.cache.ttl_seconds', 3600),
                fn () => $this->loadFromDatabase()
            )
            : $this->loadFromDatabase();

        return collect($plain)
            ->map(fn (array $names) => collect($names));
    }

    /**
     * The cached payload MUST stay plain arrays/scalars: persistent cache
     * stores refuse to unserialize objects by default (config/cache.php →
     * serializable_classes = false, Laravel's gadget-chain hardening), so
     * a cached Collection would come back as __PHP_Incomplete_Class on the
     * next process and fatal every permission check.
     *
     * @return array<int, array<int, string>> role id => permission names
     */
    protected function loadFromDatabase(): array
    {
        $tables = config('permission.table_names');

        return DB::table($tables['role_has_permissions'])
            ->join($tables['permissions'], "{$tables['permissions']}.id", '=', "{$tables['role_has_permissions']}.permission_id")
            ->get(["{$tables['role_has_permissions']}.role_id", "{$tables['permissions']}.name"])
            ->groupBy('role_id')
            ->map(fn (Collection $rows) => $rows->pluck('name')->all())
            ->all();
    }

    public function forgetCache(): void
    {
        Cache::forget(config('permission.cache.key', 'local.permission.role_permission_map'));
    }
}
