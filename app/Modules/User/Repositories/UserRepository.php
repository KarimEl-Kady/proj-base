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

    public function findByEmail(string $email): ?User
    {
        return $this->query()->where('email', $email)->first();
    }
}
