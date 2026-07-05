<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tables = config('permission.table_names');
        $morphKey = config('permission.column_names.model_morph_key', 'model_id');

        Schema::create($tables['permissions'], function (Blueprint $table) {
            $table->id();
            $table->string('name');
            // Not nullable: SQL unique constraints treat every NULL as
            // distinct, so a nullable guard_name would let duplicate
            // "no guard" permissions slip past the constraint below. Both
            // models default it to config('auth.defaults.guard') via a
            // creating() hook before it ever reaches the database.
            $table->string('guard_name');
            $table->string('description')->nullable();
            $table->timestamps();

            $table->unique(['name', 'guard_name']);
        });

        Schema::create($tables['roles'], function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('guard_name');
            $table->string('description')->nullable();
            $table->timestamps();

            $table->unique(['name', 'guard_name']);
        });

        Schema::create($tables['role_has_permissions'], function (Blueprint $table) use ($tables) {
            $table->foreignId('permission_id')->constrained($tables['permissions'])->cascadeOnDelete();
            $table->foreignId('role_id')->constrained($tables['roles'])->cascadeOnDelete();

            $table->primary(['permission_id', 'role_id']);
        });

        Schema::create($tables['model_has_roles'], function (Blueprint $table) use ($tables, $morphKey) {
            $table->foreignId('role_id')->constrained($tables['roles'])->cascadeOnDelete();
            $table->string('model_type');
            $table->unsignedBigInteger($morphKey);

            $table->index(['model_type', $morphKey], 'model_has_roles_model_index');
            $table->primary(['role_id', 'model_type', $morphKey], 'model_has_roles_primary');
        });

        Schema::create($tables['model_has_permissions'], function (Blueprint $table) use ($tables, $morphKey) {
            $table->foreignId('permission_id')->constrained($tables['permissions'])->cascadeOnDelete();
            $table->string('model_type');
            $table->unsignedBigInteger($morphKey);

            $table->index(['model_type', $morphKey], 'model_has_permissions_model_index');
            $table->primary(['permission_id', 'model_type', $morphKey], 'model_has_permissions_primary');
        });
    }

    public function down(): void
    {
        $tables = config('permission.table_names');

        Schema::dropIfExists($tables['model_has_permissions']);
        Schema::dropIfExists($tables['model_has_roles']);
        Schema::dropIfExists($tables['role_has_permissions']);
        Schema::dropIfExists($tables['roles']);
        Schema::dropIfExists($tables['permissions']);
    }
};
