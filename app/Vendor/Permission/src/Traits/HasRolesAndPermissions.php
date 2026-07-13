<?php

namespace Local\Permission\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Local\Permission\Models\Permission;
use Local\Permission\Models\Role;
use Local\Permission\Support\PermissionRegistry;

/**
 * Convenience trait for models that need both — the common case (e.g. the
 * User model). Add HasRoles or HasPermissions individually if a model only
 * needs one side.
 *
 * The role-aware permission logic lives here rather than in HasPermissions
 * because this is the only place roles() is guaranteed to exist: a model
 * with HasPermissions alone has no roles, so "direct grants only" is the
 * correct — and now type-checkable — answer there.
 *
 * @phpstan-require-extends Model
 */
trait HasRolesAndPermissions
{
    use HasPermissions;
    use HasRoles;

    /**
     * Direct grant OR granted via any assigned role.
     */
    public function hasPermissionTo(string|Permission $permission): bool
    {
        $name = $permission instanceof Permission ? $permission->name : $permission;

        if ($this->hasDirectPermission($name)) {
            return true;
        }

        $registry = app(PermissionRegistry::class);

        foreach ($this->roles as $role) {
            if ($registry->permissionNamesForRole($role->id)->contains($name)) {
                return true;
            }
        }

        return false;
    }

    /**
     * All permission names this model has: direct grants + via roles.
     *
     * @return Collection<int, string>
     */
    public function getAllPermissions(): Collection
    {
        $registry = app(PermissionRegistry::class);

        $viaRoles = $this->roles->flatMap(
            fn (Role $role): Collection => $registry->permissionNamesForRole($role->id)
        );

        return $this->permissions
            ->pluck('name')
            ->merge($viaRoles)
            ->unique()
            ->values();
    }
}
