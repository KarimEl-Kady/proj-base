<?php

namespace App\Modules\Core\Repositories\Search;

use Illuminate\Database\Eloquent\Builder;

/**
 * Opt-in replacement for LikeSearch on tables that have actually grown
 * large enough for a leading-wildcard LIKE to matter. Requires the
 * searchable columns to carry a real fulltext index (MySQL FULLTEXT /
 * PostgreSQL tsvector) — add that in a migration before switching a
 * repository to this strategy.
 *
 * Falls back to LikeSearch on any driver without native fulltext support
 * (SQLite, notably — this base ships SQLite as a real, documented
 * deployment option for smaller projects, not just a test fixture, so a
 * repository that opts into FullTextSearch must not go down in production
 * just because that project chose SQLite). The fallback is exact
 * LikeSearch behavior, not an approximation — a repository's existing
 * word-filter tests keep passing unchanged under the SQLite test suite,
 * and MySQL/PostgreSQL genuinely exercise fulltext, verified separately
 * against a real MySQL connection (see FullTextSearchTest, driver-gated).
 * Opt in per repository by overriding BaseRepository::searchStrategy().
 */
class FullTextSearch implements SearchStrategy
{
    private const SUPPORTED_DRIVERS = ['mysql', 'pgsql'];

    public function apply(Builder $query, string $word, array $columns): Builder
    {
        if (! in_array($query->getModel()->getConnection()->getDriverName(), self::SUPPORTED_DRIVERS, true)) {
            return (new LikeSearch)->apply($query, $word, $columns);
        }

        return $query->whereFullText($columns, $word);
    }
}
