<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('password_reset_tokens', 'user_uuid')) {
            return;
        }

        // Reset tokens are ephemeral. Rebuilding the table is portable across
        // SQLite, MySQL, and PostgreSQL and intentionally invalidates every
        // outstanding legacy email-only token during this security upgrade.
        Schema::dropIfExists('password_reset_tokens');
        $this->createUuidBoundTable();
    }

    public function down(): void
    {
        if (! Schema::hasColumn('password_reset_tokens', 'user_uuid')) {
            return;
        }

        Schema::dropIfExists('password_reset_tokens');
        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }

    protected function createUuidBoundTable(): void
    {
        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->uuid('user_uuid')->primary();
            $table->string('email')->index();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }
};
