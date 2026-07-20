<?php

namespace App\Modules\Core\Repositories\Search;

use Illuminate\Database\Eloquent\Builder;

/**
 * How BaseRepository::fetch() applies the `?word=` filter across a model's
 * $searchable columns. See BaseRepository::searchStrategy() for how a
 * repository opts into something other than the LikeSearch default.
 */
interface SearchStrategy
{
    /**
     * @param  array<int, string>  $columns
     */
    public function apply(Builder $query, string $word, array $columns): Builder;
}
