<?php

namespace Local\Permission\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use Local\Permission\Support\PermissionRegistry;

/**
 * @property int $id
 * @property int|string|null $tenant_id Null = global role. findOrCreate()/
 *                                      findByName() only ever resolve global roles; a host app that
 *                                      wants per-tenant custom roles uses findOrCreateForTenant()/
 *                                      findByNameForTenant() instead — this package makes no
 *                                      assumption about tenancy being enabled at all, so nothing
 *                                      infers a tenant from ambient context on its own.
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

    /**
     * Global roles only (tenant_id IS NULL) — explicit in the query, not
     * just "whatever the default column value happens to be", so this
     * can't accidentally resolve to a tenant-scoped role of the same
     * name/guard once those can exist. See findByNameForTenant() for the
     * tenant-scoped lookup.
     */
    public static function findByName(string $name, ?string $guardName = null): self
    {
        $guardName ??= static::defaultGuardName();

        $role = static::query()->whereNull('tenant_id')->where('name', $name)->where('guard_name', $guardName)->first();

        if ($role === null) {
            throw new InvalidArgumentException("Role [{$name}] does not exist.");
        }

        return $role;
    }

    /**
     * Global roles only (tenant_id IS NULL) — see findByName()'s docblock.
     */
    public static function findOrCreate(string $name, ?string $guardName = null): self
    {
        $guardName ??= static::defaultGuardName();

        return static::query()->firstOrCreate(
            ['tenant_id' => null, 'name' => $name, 'guard_name' => $guardName]
        );
    }

    /**
     * The tenant-scoped counterpart to findByName(): looks up a role
     * belonging to exactly $tenantId (pass null for the global role,
     * equivalent to findByName() itself). A host app opts into per-tenant
     * roles by calling this explicitly — nothing here infers a tenant from
     * ambient request context on its own, matching the rest of this
     * package's host-agnostic, nothing-assumed-about-tenancy design.
     */
    public static function findByNameForTenant(int|string|null $tenantId, string $name, ?string $guardName = null): self
    {
        $guardName ??= static::defaultGuardName();

        $role = static::query()
            ->where('tenant_id', $tenantId)
            ->where('name', $name)
            ->where('guard_name', $guardName)
            ->first();

        if ($role === null) {
            $scope = $tenantId === null ? 'global' : "tenant [{$tenantId}]";

            throw new InvalidArgumentException("Role [{$name}] does not exist for {$scope}.");
        }

        return $role;
    }

    /**
     * The tenant-scoped counterpart to findOrCreate(): two different
     * tenants (or a tenant and the global scope) can each own a role named
     * "admin" without colliding — see the 2026_07_21_000001 migration for
     * why that requires a composite unique index, not just this column
     * existing.
     */
    public static function findOrCreateForTenant(int|string|null $tenantId, string $name, ?string $guardName = null): self
    {
        $guardName ??= static::defaultGuardName();

        return static::query()->firstOrCreate(
            ['tenant_id' => $tenantId, 'name' => $name, 'guard_name' => $guardName]
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
