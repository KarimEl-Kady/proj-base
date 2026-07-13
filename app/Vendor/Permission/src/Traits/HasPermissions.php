<?php

namespace Local\Permission\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Collection;
use Local\Permission\Models\Permission;

/**
 * Direct (role-free) permission grants. Compose with HasRoles — or use
 * HasRolesAndPermissions, which wires both together — for role-granted
 * permissions as well.
 *
 * @phpstan-require-extends Model
 */
trait HasPermissions
{
    /**
     * Permissions granted directly to this model, bypassing roles.
     *
     * @return MorphToMany<Permission, $this>
     */
    public function permissions(): MorphToMany
    {
        return $this->morphToMany(
            static::permissionClass(),
            'model',
            config('permission.table_names.model_has_permissions'),
            config('permission.column_names.model_morph_key', 'model_id'),
            'permission_id'
        );
    }

    /**
     * The configured Permission model. Validated rather than trusted: a
     * config typo would otherwise surface as an obscure relation error.
     *
     * @return class-string<Permission>
     */
    protected static function permissionClass(): string
    {
        $class = config('permission.models.permission', Permission::class);

        return is_string($class) && is_a($class, Permission::class, true)
            ? $class
            : Permission::class;
    }

    /**
     * @param  string|Permission|array<int, string|Permission>  ...$permissions
     */
    public function givePermissionTo(...$permissions): static
    {
        $this->permissions()->syncWithoutDetaching($this->resolvePermissionIds($permissions));
        $this->unsetRelation('permissions');

        return $this;
    }

    /**
     * @param  string|Permission|array<int, string|Permission>  ...$permissions
     */
    public function revokePermissionTo(...$permissions): static
    {
        $this->permissions()->detach($this->resolvePermissionIds($permissions));
        $this->unsetRelation('permissions');

        return $this;
    }

    /**
     * @param  string|Permission|array<int, string|Permission>  ...$permissions
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
     * Direct grants only. Models that also carry roles use
     * HasRolesAndPermissions, which overrides this to fall back to the
     * role-granted permissions as well.
     */
    public function hasPermissionTo(string|Permission $permission): bool
    {
        $name = $permission instanceof Permission ? $permission->name : $permission;

        return $this->hasDirectPermission($name);
    }

    /**
     * @param  string|Permission|array<int, string|Permission>  ...$permissions
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
     * @param  string|Permission|array<int, string|Permission>  ...$permissions
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
     * Directly-granted permission names. HasRolesAndPermissions overrides
     * this to merge in the ones granted via roles.
     *
     * @return Collection<int, string>
     */
    public function getAllPermissions(): Collection
    {
        return $this->permissions->pluck('name')->unique()->values();
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
