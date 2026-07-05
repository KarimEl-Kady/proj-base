<?php

namespace Local\Permission\Traits;

use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Collection;
use Local\Permission\Models\Permission;
use Local\Permission\Models\Role;
use Local\Permission\Support\PermissionRegistry;

trait HasPermissions
{
    /**
     * Permissions granted directly to this model, bypassing roles.
     */
    public function permissions(): MorphToMany
    {
        return $this->morphToMany(
            config('permission.models.permission', Permission::class),
            'model',
            config('permission.table_names.model_has_permissions'),
            config('permission.column_names.model_morph_key', 'model_id'),
            'permission_id'
        );
    }

    /**
     * @param  string|Permission  ...$permissions
     */
    public function givePermissionTo(...$permissions): static
    {
        $this->permissions()->syncWithoutDetaching($this->resolvePermissionIds($permissions));
        $this->unsetRelation('permissions');

        return $this;
    }

    /**
     * @param  string|Permission  ...$permissions
     */
    public function revokePermissionTo(...$permissions): static
    {
        $this->permissions()->detach($this->resolvePermissionIds($permissions));
        $this->unsetRelation('permissions');

        return $this;
    }

    /**
     * @param  string|Permission  ...$permissions
     */
    public function syncPermissions(...$permissions): static
    {
        $this->permissions()->sync($this->resolvePermissionIds($permissions));
        $this->unsetRelation('permissions');

        return $this;
    }

    public function hasDirectPermission(string|Permission $permission): bool
    {
        $name = $permission instanceof Permission ? $permission->name : $permission;

        return $this->permissions->contains('name', $name);
    }

    /**
     * Direct grant OR granted via any assigned role.
     */
    public function hasPermissionTo(string|Permission $permission): bool
    {
        $name = $permission instanceof Permission ? $permission->name : $permission;

        if ($this->hasDirectPermission($name)) {
            return true;
        }

        if (! method_exists($this, 'roles')) {
            return false;
        }

        $registry = app(PermissionRegistry::class);

        foreach ($this->roles as $role) {
            /** @var Role $role */
            if ($registry->permissionNamesForRole($role->id)->contains($name)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  string|Permission  ...$permissions
     */
    public function hasAnyPermission(...$permissions): bool
    {
        foreach (Collection::make($permissions)->flatten() as $permission) {
            if ($this->hasPermissionTo($permission)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  string|Permission  ...$permissions
     */
    public function hasAllPermissions(...$permissions): bool
    {
        foreach (Collection::make($permissions)->flatten() as $permission) {
            if (! $this->hasPermissionTo($permission)) {
                return false;
            }
        }

        return true;
    }

    /**
     * All permission names this model has, direct + via roles.
     *
     * @return Collection<int, string>
     */
    public function getAllPermissions(): Collection
    {
        $direct = $this->permissions->pluck('name');

        if (! method_exists($this, 'roles')) {
            return $direct->unique()->values();
        }

        $registry = app(PermissionRegistry::class);

        $viaRoles = $this->roles->flatMap(
            fn (Role $role) => $registry->permissionNamesForRole($role->id)
        );

        return $direct->merge($viaRoles)->unique()->values();
    }

    /**
     * @param  array<int, string|Permission>  $permissions
     * @return array<int, int>
     */
    protected function resolvePermissionIds(array $permissions): array
    {
        return Collection::make($permissions)
            ->flatten()
            ->map(fn (string|Permission $permission) => $permission instanceof Permission
                ? $permission->id
                : Permission::findOrCreate($permission)->id)
            ->all();
    }
}
