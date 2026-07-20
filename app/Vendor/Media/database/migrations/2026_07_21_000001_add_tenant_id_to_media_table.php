<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media', function (Blueprint $table): void {
            $table->unsignedBigInteger('tenant_id')->nullable()->index()->after('id');
            $table->index(['tenant_id', 'mediable_type', 'mediable_id'], 'media_tenant_mediable_index');
        });
    }

    public function down(): void
    {
        Schema::table('media', function (Blueprint $table): void {
            $table->dropIndex('media_tenant_mediable_index');
            $table->dropColumn('tenant_id');
        });
    }
};
