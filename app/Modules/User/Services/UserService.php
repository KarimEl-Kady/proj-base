<?php

namespace App\Modules\User\Services;

use App\Modules\Core\Services\BaseService;
use App\Modules\User\Models\User;
use App\Modules\User\Repositories\UserRepository;
use Illuminate\Support\Facades\Hash;

class UserService extends BaseService
{
    public function __construct(UserRepository $repository)
    {
        parent::__construct($repository);
    }

    public function create(array $data): User
    {
        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }

        /** @var User */
        return parent::create($data);
    }

    public function update(int|string $id, array $data): User
    {
        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }

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
