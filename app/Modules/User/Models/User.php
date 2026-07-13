<?php

namespace App\Modules\User\Models;

use App\Modules\Core\Models\Model;
use App\Modules\Core\Traits\HasTenantScope;
use App\Modules\User\Events\UserCreated;
use Illuminate\Auth\Authenticatable;
use Illuminate\Auth\MustVerifyEmail;
use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Contracts\Auth\Access\Authorizable as AuthorizableContract;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;
use Illuminate\Contracts\Auth\MustVerifyEmail as MustVerifyEmailContract;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Foundation\Auth\Access\Authorizable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\HasApiTokens;
use Local\Permission\Traits\HasRolesAndPermissions;

/**
 * Deliberately does NOT re-import HasFactory — Core\Models\Model already
 * provides it, and re-declaring the trait here would shadow Model's
 * newFactory() override with the trait's own (returns null unless
 * static::$factory is set), breaking User::factory() resolution. See
 * App\Modules\User\Database\Factories\UserFactory.
 *
 * MustVerifyEmailContract must be declared alongside the trait, not just
 * the trait alone: Laravel's own `verified` middleware and the Verified
 * event both gate on `instanceof MustVerifyEmail`, so a model carrying only
 * the trait silently skips verification enforcement.
 *
 * @property string $uuid
 * @property string $name
 * @property string $email
 * @property string $password
 * @property ?string $two_factor_secret
 * @property ?Carbon $two_factor_confirmed_at
 * @property ?Carbon $email_verified_at
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token', 'two_factor_secret'])]
class User extends Model implements AuthenticatableContract, AuthorizableContract, CanResetPasswordContract, MustVerifyEmailContract
{
    use Authenticatable, Authorizable, CanResetPassword, MustVerifyEmail;
    use HasApiTokens, Notifiable;
    use HasRolesAndPermissions;
    use HasTenantScope;

    protected $table = 'users';

    /** Columns matched by the FetchRequest `word` filter. */
    protected array $searchable = ['name', 'email'];

    protected static function booted(): void
    {
        // Model hook (not service-layer dispatch) so the fact is emitted no
        // matter which entry point created the user: UserService CRUD,
        // Auth registration, factories, seeders.
        static::created(fn (User $user) => UserCreated::dispatch($user));
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_secret' => 'encrypted',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    public function hasTwoFactorEnabled(): bool
    {
        return $this->two_factor_secret !== null && $this->two_factor_confirmed_at !== null;
    }
}
