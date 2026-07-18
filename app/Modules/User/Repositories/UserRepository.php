<?php

namespace App\Modules\User\Repositories;

use App\Modules\Core\Repositories\BaseRepository;
use App\Modules\User\Models\User;

class UserRepository extends BaseRepository
{
    public function __construct(User $model)
    {
        parent::__construct($model);
    }

    /**
     * Queries User directly rather than through BaseRepository::query(),
     * whose builder is typed to the base Model — this keeps the ?User
     * return type honest rather than annotated.
     */
    public function findByEmail(string $email): ?User
    {
        return User::query()->where('email', mb_strtolower(trim($email)))->first();
    }
}
