<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Backs UserRepository::searchStrategy()'s FullTextSearch adoption (see
 * that class and app/Modules/Core/Repositories/Search/FullTextSearch.php).
 * MySQL FULLTEXT / PostgreSQL GIN via to_tsvector — no equivalent exists
 * for SQLite, so this is a genuine no-op there rather than something that
 * would fail migrate:fresh on the driver the default test suite runs on.
 * FullTextSearch itself already falls back to LikeSearch on any driver
 * without this index, so skipping it here is consistent, not a partial
 * feature.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! in_array(DB::connection()->getDriverName(), ['mysql', 'pgsql'], true)) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->fullText(['name', 'email']);
        });
    }

    public function down(): void
    {
        if (! in_array(DB::connection()->getDriverName(), ['mysql', 'pgsql'], true)) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropFullText(['name', 'email']);
        });
    }
};
