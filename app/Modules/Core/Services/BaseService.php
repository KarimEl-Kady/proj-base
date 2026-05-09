<?php

namespace App\Modules\Core\Services;

use App\Modules\Core\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;

abstract class BaseService
{
    public function __construct(
        protected BaseRepository $repository
    ) {}

    public function find(int|string $id): ?Model
    {
        return $this->repository->find($id);
    }

    public function findOrFail(int|string $id): Model
    {
        return $this->repository->findOrFail($id);
    }

    public function all(): Collection
    {
        return $this->repository->all();
    }

    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return $this->repository->paginate($perPage);
    }

    public function create(array $data): Model
    {
        return $this->repository->create($data);
    }

    public function update(int|string $id, array $data): Model
    {
        $model = $this->repository->findOrFail($id);
        $this->repository->update($model, $data);

        return $model->fresh();
    }

    public function delete(int|string $id): bool
    {
        $model = $this->repository->findOrFail($id);

        return (bool) $this->repository->delete($model);
    }
}
