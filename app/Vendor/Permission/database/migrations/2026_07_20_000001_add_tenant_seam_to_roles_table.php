<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A nullable, unconstrained seam — not a feature. Null tenant_id (the
 * default, and the only value anything writes today) means the role is
 * global, exactly as every role is now. A host app that later needs
 * per-tenant custom roles can set tenant_id without a breaking migration.
 *
 * No foreign key: this package must stay host-agnostic (no App\ imports,
 * no assumption about the host's tenant table name or even that tenancy
 * is enabled at all) — same reasoning as Core's own OutboxMessage.tenant_id.
 */
return new class extends Migration
{
    public function up(): void
    {
        $table = config('permission.table_names.roles', 'roles');

        Schema::table($table, function (Blueprint $table) {
            $table->unsignedBigInteger('tenant_id')->nullable()->index()->after('id');
        });
    }

    public function down(): void
    {
        $table = config('permission.table_names.roles', 'roles');

        Schema::table($table, function (Blueprint $table) {
            $table->dropColumn('tenant_id');
        });
    }
};
