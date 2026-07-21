<?php

namespace App\Modules\User\Repositories;

use App\Modules\Core\Repositories\BaseRepository;
use App\Modules\Core\Repositories\Search\FullTextSearch;
use App\Modules\Core\Repositories\Search\SearchStrategy;
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

    /**
     * The base's first real FullTextSearch adoption — see that class's
     * docblock and the 2026_07_21_000002 migration for the paired fulltext
     * index. Users is the table this project's own scale story (see
     * docs/architecture.md's Scaling Path) actually names, and the driver
     * fallback means this is safe on every supported database, not just
     * MySQL/PostgreSQL in production.
     */
    protected function searchStrategy(): SearchStrategy
    {
        return new FullTextSearch;
    }
}
