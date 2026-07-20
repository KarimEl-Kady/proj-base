<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique()->nullable();
            $table->tenantColumn();
            $table->string('name');
            $table->string('email');
            // Unconditional composite unique, matching every other tenant
            // column: the schema no longer forks on tenancy.mode (see
            // tenantColumn()'s docblock). In "none"/"single" mode tenant_id
            // is the same value (null, or the one implicit tenant) on every
            // row, so this constrains email exactly like a plain unique
            // would — except SQL unique indexes treat every NULL as
            // distinct, so in "none" mode this index alone would not stop
            // two rows both holding tenant_id=NULL from sharing an email.
            // UserRules::uniqueEmail() is the real backstop there: it runs
            // a global (unscoped) uniqueness check whenever has_tenancy()
            // is false, independent of this index. Only a direct DB write
            // that bypasses application validation could exploit the gap.
            $table->unique([config('project.tenancy.tenant_column', 'tenant_id'), 'email']);
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
