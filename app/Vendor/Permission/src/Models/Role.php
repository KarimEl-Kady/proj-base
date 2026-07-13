<?php

namespace Local\Permission\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use Local\Permission\Support\PermissionRegistry;

/**
 * @property int $id
 * @property string $name
 * @property string $guard_name
 * @property ?string $description
 */
class Role extends Model
{
    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::creating(function (self $role) {
            $role->guard_name ??= static::defaultGuardName();
        });

        static::saved(fn () => app(PermissionRegistry::class)->forgetCache());
        static::deleted(fn () => app(PermissionRegistry::class)->forgetCache());
    }

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->table = config('permission.table_names.roles', 'roles');
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(
            Permission::class,
            config('permission.table_names.role_has_permissions'),
            'role_id',
            'permission_id'
        );
    }

    /**
     * @param  string|Permission|array<int, string|Permission>  ...$permissions
     */
    public function givePermissionTo(...$permissions): static
    {
        $this->permissions()->syncWithoutDetaching($this->resolvePermissionIds($permissions));
        app(PermissionRegistry::class)->forgetCache();

        return $this;
    }

    /**
     * @param  string|Permission|array<int, string|Permission>  ...$permissions
     */
    public function revokePermissionTo(...$permissions): static
    {
        $this->permissions()->detach($this->resolvePermissionIds($permissions));
        app(PermissionRegistry::class)->forgetCache();

        return $this;
    }

    /**
     * @param  string|Permission|array<int, string|Permission>  ...$permissions
     */
    public function syncPermissions(...$permissions): static
    {
        $this->permissions()->sync($this->resolvePermissionIds($permissions));
        app(PermissionRegistry::class)->forgetCache();

        return $this;
    }

    public function hasPermissionTo(string|Permission $permission): bool
    {
        $name = $permission instanceof Permission ? $permission->name : $permission;

        return $this->permissions->contains('name', $name);
    }

    public static function findByName(string $name, ?string $guardName = null): self
    {
        $guardName ??= static::defaultGuardName();

        $role = static::query()->where('name', $name)->where('guard_name', $guardName)->first();

        if ($role === null) {
            throw new InvalidArgumentException("Role [{$name}] does not exist.");
        }

        return $role;
    }

    public static function findOrCreate(string $name, ?string $guardName = null): self
    {
        $guardName ??= static::defaultGuardName();

        return static::query()->firstOrCreate(
            ['name' => $name, 'guard_name' => $guardName]
        );
    }

    protected static function defaultGuardName(): string
    {
        return config('auth.defaults.guard', 'web');
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
