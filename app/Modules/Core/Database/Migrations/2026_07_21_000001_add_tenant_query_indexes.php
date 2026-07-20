<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->index(['tenant_id', 'occurred_at'], 'audit_logs_tenant_occurred_index');
        });

        if (Schema::hasColumn('users', 'tenant_id')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->index(['tenant_id', 'id'], 'users_tenant_pk_index');
            });
        }
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->dropIndex('audit_logs_tenant_occurred_index');
        });

        if (Schema::hasColumn('users', 'tenant_id')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropIndex('users_tenant_pk_index');
            });
        }
    }
};
