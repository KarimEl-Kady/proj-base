<?php

namespace App\Modules\Core\Tests\Unit;

use App\Modules\Core\Repositories\Search\LikeSearch;
use App\Modules\Core\Repositories\Search\SearchStrategy;
use App\Modules\User\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Tests\TestCase;

class SearchStrategyTest extends TestCase
{
    public function test_like_search_builds_an_escaped_leading_wildcard_query(): void
    {
        $query = (new LikeSearch)->apply(User::query(), 'o%_reilly', ['name', 'email']);

        $sql = $query->toSql();
        $bindings = $query->getBindings();

        $this->assertStringContainsString('like ? escape', $sql);
        $this->assertCount(2, $bindings);
        $this->assertSame('%o!%!_reilly%', $bindings[0]);
        $this->assertSame('%o!%!_reilly%', $bindings[1]);
    }

    public function test_a_repository_can_swap_the_default_strategy(): void
    {
        $custom = new class implements SearchStrategy
        {
            public bool $called = false;

            public function apply(Builder $query, string $word, array $columns): Builder
            {
                $this->called = true;

                return $query->where('name', $word);
            }
        };

        $query = $custom->apply(User::query(), 'exact-match', ['name']);

        $this->assertTrue($custom->called);
        $this->assertStringNotContainsString('like', $query->toSql());
    }
}
