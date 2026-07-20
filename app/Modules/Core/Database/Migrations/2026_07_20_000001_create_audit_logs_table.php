<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('actor_type')->nullable();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('action');
            $table->string('auditable_type');
            $table->unsignedBigInteger('auditable_id');
            // Unconstrained, like OutboxMessage.tenant_id — Core is the
            // platform layer tenancy itself is built from, so it can't
            // assume the host's tenant table/column shape any more than
            // a local/* package can.
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->json('changes')->nullable();
            $table->timestamp('occurred_at')->index();
            $table->timestamps();

            $table->index(['auditable_type', 'auditable_id']);
            $table->index(['actor_type', 'actor_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
