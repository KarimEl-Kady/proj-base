<?php

namespace App\Modules\User\Models;

use App\Modules\Core\Models\Model;
use App\Modules\Core\Traits\HasTenantScope;
use Database\Factories\UserFactory;
use Illuminate\Auth\Authenticatable;
use Illuminate\Auth\MustVerifyEmail;
use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Contracts\Auth\Access\Authorizable as AuthorizableContract;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\Access\Authorizable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Model implements AuthenticatableContract, AuthorizableContract, CanResetPasswordContract
{
    /** @use HasFactory<UserFactory> */
    use Authenticatable, Authorizable, CanResetPassword, MustVerifyEmail;

    use HasFactory, Notifiable;
    use HasTenantScope;

    protected $table = 'users';

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
