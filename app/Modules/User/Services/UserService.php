<?php

namespace App\Modules\User\Services;

use App\Modules\Core\Services\BaseService;
use App\Modules\User\Models\User;
use App\Modules\User\Repositories\UserRepository;

class UserService extends BaseService
{
    public function __construct(UserRepository $repository)
    {
        parent::__construct($repository);
    }

    /**
     * Password hashing is not done here: User::casts()['password'] is
     * 'hashed', so every write path (this service, AuthService::register,
     * MakeAdminCommand, factories) hashes through the same single mechanism
     * — the model — instead of each caller owning its own Hash::make() call.
     */
    public function create(array $data): User
    {
        /** @var User */
        return parent::create($data);
    }

    public function update(int|string $id, array $data): User
    {
        /** @var User */
        return parent::update($id, $data);
    }

    public function findByEmail(string $email): ?User
    {
        /** @var UserRepository $repo */
        $repo = $this->repository;

        return $repo->findByEmail($email);
    }
}
