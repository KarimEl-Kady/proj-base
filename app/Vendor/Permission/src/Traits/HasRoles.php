<?php

namespace Local\Permission\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Collection;
use Local\Permission\Models\Role;

/**
 * @phpstan-require-extends Model
 */
trait HasRoles
{
    /**
     * @return MorphToMany<Role, $this>
     */
    public function roles(): MorphToMany
    {
        return $this->morphToMany(
            static::roleClass(),
            'model',
            config('permission.table_names.model_has_roles'),
            config('permission.column_names.model_morph_key', 'model_id'),
            'role_id'
        );
    }

    /**
     * The configured Role model. Validated rather than trusted: a config
     * typo would otherwise surface as an obscure relation error.
     *
     * @return class-string<Role>
     */
    protected static function roleClass(): string
    {
        $class = config('permission.models.role', Role::class);

        return is_string($class) && is_a($class, Role::class, true)
            ? $class
            : Role::class;
    }

    /**
     * @param  string|Role|array<int, string|Role>  ...$roles
     */
    public function assignRole(...$roles): static
    {
        $this->roles()->syncWithoutDetaching($this->resolveRoleIds($roles));
        $this->unsetRelation('roles');

        return $this;
    }

    /**
     * @param  string|Role|array<int, string|Role>  ...$roles
     */
    public function removeRole(...$roles): static
    {
        $this->roles()->detach($this->resolveRoleIds($roles));
        $this->unsetRelation('roles');

        return $this;
    }

    /**
     * @param  string|Role|array<int, string|Role>  ...$roles
     */
    public function syncRoles(...$roles): static
    {
        $this->roles()->sync($this->resolveRoleIds($roles));
        $this->unsetRelation('roles');

        return $this;
    }

    public function hasRole(string|Role $role): bool
    {
        $name = $role instanceof Role ? $role->name : $role;

        return $this->roles->contains('name', $name);
    }

    /**
     * @param  string|Role|array<int, string|Role>  ...$roles
     */
    public function hasAnyRole(...$roles): bool
    {
        $names = $this->roleNames($roles);

        return $this->roles->pluck('name')->intersect($names)->isNotEmpty();
    }

    /**
     * @param  string|Role|array<int, string|Role>  ...$roles
     */
    public function hasAllRoles(...$roles): bool
    {
        $names = collect($this->roleNames($roles));
        $mine = $this->roles->pluck('name');

        return $names->diff($mine)->isEmpty();
    }

    /**
     * @param  array<int, string|Role>  $roles
     * @return array<int, string>
     */
    protected function roleNames(array $roles): array
    {
        return Collection::make($roles)
            ->flatten()
            ->map(fn (string|Role $role) => $role instanceof Role ? $role->name : $role)
            ->all();
    }

    /**
     * @param  array<int, string|Role>  $roles
     * @return array<int, int>
     */
    protected function resolveRoleIds(array $roles): array
    {
        return Collection::make($roles)
            ->flatten()
            ->map(fn (string|Role $role) => $role instanceof Role ? $role->id : Role::findOrCreate($role)->id)
            ->all();
    }
}
