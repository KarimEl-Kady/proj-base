<?php

namespace App\Modules\User\Tests\Feature;

use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * UserRepository::searchStrategy() opts into FullTextSearch (see that
 * class + the 2026_07_21_000002 migration). The driver-aware fallback to
 * LikeSearch is already covered by UserApiTest's word-filter tests, which
 * run — and must keep passing — against this suite's SQLite connection.
 * What SQLite can't verify is the fulltext path itself, so this test is
 * driver-gated and skips everywhere except a real MySQL/PostgreSQL
 * connection (e.g. `DB_CONNECTION=mysql php artisan test --filter=
 * UserFullTextSearchTest` against the Docker mysql service).
 *
 * DatabaseTruncation, not RefreshDatabase: InnoDB's FULLTEXT index only
 * sees a row once its insert transaction commits — RefreshDatabase wraps
 * every test in a transaction that's rolled back, never committed, so
 * MATCH AGAINST would find nothing regardless of whether the feature
 * works. Reproduced directly: an insert inside an open, uncommitted
 * transaction returned a fulltext match count of 0 against the exact same
 * row a plain WHERE found immediately. Truncation actually commits.
 */
class UserFullTextSearchTest extends TestCase
{
    use DatabaseTruncation;

    protected function setUp(): void
    {
        parent::setUp();

        if (! in_array('User', config('project.modules'))) {
            $this->markTestSkipped('Module [User] is disabled.');
        }

        if (! in_array(DB::connection()->getDriverName(), ['mysql', 'pgsql'], true)) {
            $this->markTestSkipped('Fulltext search requires a real MySQL/PostgreSQL connection.');
        }
    }

    protected function makeUser(string $name, string $email): User
    {
        return $this->withTestTenant(null, fn () => User::query()->create([
            'name' => $name,
            'email' => $email,
            'password' => 'secret123',
        ]));
    }

    public function test_word_filter_uses_a_real_fulltext_match_against_mysql_or_postgres(): void
    {
        $alice = $this->makeUser('Alice Johnson', 'alice@example.com');
        $this->makeUser('Bob Smith', 'bob@example.com');
        $alice->givePermissionTo('users.view');
        $this->actingAsUser('users.view');

        $response = $this->getJson('/api/v1/users?word=Johnson');

        $response->assertOk();
        $this->assertCount(1, $response->json('data.data'));
        $this->assertSame('Alice Johnson', $response->json('data.data.0.name'));
    }

    public function test_fulltext_index_actually_exists_on_the_searchable_columns(): void
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            $columns = collect(DB::select("SHOW INDEX FROM users WHERE Key_name = 'users_name_email_fulltext'"))
                ->pluck('Column_name');

            $this->assertTrue($columns->contains('name'));
            $this->assertTrue($columns->contains('email'));

            return;
        }

        $definitions = collect(DB::select("SELECT indexdef FROM pg_indexes WHERE tablename = 'users'"))
            ->pluck('indexdef')
            ->implode(' ');

        $this->assertStringContainsString('name', $definitions);
        $this->assertStringContainsString('email', $definitions);
    }
}
