<?php

namespace App\Modules\Core\Repositories\Search;

use Illuminate\Database\Eloquent\Builder;

/**
 * Opt-in replacement for LikeSearch on tables that have actually grown
 * large enough for a leading-wildcard LIKE to matter. Requires the
 * searchable columns to carry a real fulltext index (MySQL FULLTEXT /
 * PostgreSQL tsvector) — add that in a migration before switching a
 * repository to this strategy, or every query will fail at the database,
 * not degrade gracefully.
 *
 * Not a default for a reason: it needs a driver that supports it (SQLite,
 * used in this project's test suite, does not) and an index that doesn't
 * exist until a module author deliberately adds one. Opt in per repository
 * by overriding BaseRepository::searchStrategy().
 */
class FullTextSearch implements SearchStrategy
{
    public function apply(Builder $query, string $word, array $columns): Builder
    {
        return $query->whereFullText($columns, $word);
    }
}
