<?php

namespace App\Modules\Core\Repositories\Search;

use Illuminate\Database\Eloquent\Builder;

/**
 * The default: `WHERE column LIKE '%word%'` OR'd across every searchable
 * column. Zero setup, correct, and fine for most modules — most tables in
 * a project this size never cross into the row counts where this matters.
 *
 * The known tradeoff: a leading wildcard can't use a B-tree index, so this
 * degrades to a full scan per searchable column as a table grows into the
 * millions of rows. When a specific module's table actually gets there,
 * override BaseRepository::searchStrategy() in that repository with
 * FullTextSearch (paired with a real fulltext index migration) rather than
 * changing this default for every module that doesn't need it.
 */
class LikeSearch implements SearchStrategy
{
    public function apply(Builder $query, string $word, array $columns): Builder
    {
        // LIKE wildcards in the search word are user data, not operators.
        // "!" as the escape char is portable (backslash escaping differs
        // between MySQL and SQLite).
        $escaped = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $word);

        return $query->where(function (Builder $inner) use ($columns, $escaped) {
            foreach ($columns as $column) {
                $wrapped = $inner->getGrammar()->wrap($inner->qualifyColumn($column));
                $inner->orWhereRaw("{$wrapped} like ? escape '!'", ["%{$escaped}%"]);
            }
        });
    }
}
