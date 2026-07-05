<?php

namespace Local\Permission\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use InvalidArgumentException;

/**
 * @property int $id
 * @property string $name
 * @property string $guard_name
 * @property ?string $description
 */
class Permission extends Model
{
    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::creating(function (self $permission) {
            $permission->guard_name ??= static::defaultGuardName();
        });
    }

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->table = config('permission.table_names.permissions', 'permissions');
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(
            Role::class,
            config('permission.table_names.role_has_permissions'),
            'permission_id',
            'role_id'
        );
    }

    public static function findByName(string $name, ?string $guardName = null): self
    {
        $guardName ??= static::defaultGuardName();

        $permission = static::query()->where('name', $name)->where('guard_name', $guardName)->first();

        if ($permission === null) {
            throw new InvalidArgumentException("Permission [{$name}] does not exist.");
        }

        return $permission;
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
}
