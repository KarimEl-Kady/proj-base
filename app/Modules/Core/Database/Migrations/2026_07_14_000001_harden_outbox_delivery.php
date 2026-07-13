<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('outbox_messages', function (Blueprint $table) {
            $table->timestamp('available_at')->nullable()->after('published_at')->index();
            $table->timestamp('claimed_at')->nullable()->after('available_at')->index();
            $table->uuid('claim_token')->nullable()->after('claimed_at')->unique();
            $table->timestamp('failed_at')->nullable()->after('claim_token')->index();
        });

        DB::table('outbox_messages')
            ->whereNull('available_at')
            ->update(['available_at' => DB::raw('occurred_at')]);

        Schema::table('outbox_messages', function (Blueprint $table) {
            $table->index(
                ['published_at', 'failed_at', 'available_at'],
                'outbox_publishable_index'
            );
        });
    }

    public function down(): void
    {
        Schema::table('outbox_messages', function (Blueprint $table) {
            $table->dropIndex('outbox_publishable_index');
            $table->dropUnique(['claim_token']);
            $table->dropColumn(['available_at', 'claimed_at', 'claim_token', 'failed_at']);
        });
    }
};
