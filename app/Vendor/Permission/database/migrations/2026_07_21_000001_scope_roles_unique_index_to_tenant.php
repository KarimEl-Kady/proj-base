<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Graduates the tenant_id seam (2026_07_20_000001) into real per-tenant role
 * resolution: Role::findOrCreateForTenant()/findByNameForTenant() let a host
 * app create a role named e.g. "admin" independently for two different
 * tenants, which the original global unique(['name', 'guard_name']) index
 * would have rejected outright as a duplicate.
 *
 * Composite unique(['tenant_id', 'name', 'guard_name']) matches the same
 * pattern the User module already uses for tenant_id+email: it's the real
 * constraint for the tenant-scoped case, but SQL treats every NULL as
 * distinct, so it does NOT stop two rows both holding tenant_id=NULL from
 * sharing a name/guard_name — i.e. it does not, by itself, protect global
 * roles. Role::findOrCreate()/findByName() (global path) are the
 * application-level backstop for that gap, exactly as UserRules::
 * uniqueEmail() is for users in "none" tenancy mode — see
 * docs/architecture.md's Tenancy section for the general rule.
 */
return new class extends Migration
{
    public function up(): void
    {
        $table = config('permission.table_names.roles', 'roles');

        Schema::table($table, function (Blueprint $table) {
            $table->dropUnique(['name', 'guard_name']);
            $table->unique(['tenant_id', 'name', 'guard_name']);
        });
    }

    public function down(): void
    {
        $table = config('permission.table_names.roles', 'roles');

        Schema::table($table, function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'name', 'guard_name']);
            $table->unique(['name', 'guard_name']);
        });
    }
};
