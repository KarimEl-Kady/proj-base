<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outbox_messages', function (Blueprint $table) {
            $table->id();
            $table->uuid('event_id')->unique();
            $table->string('type');
            $table->json('payload');
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->string('request_id', 128)->nullable()->index();
            $table->timestamp('occurred_at');
            $table->timestamp('published_at')->nullable()->index();
            $table->unsignedInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamps();
        });

        Schema::create('processed_messages', function (Blueprint $table) {
            $table->id();
            $table->uuid('event_id');
            $table->string('consumer');
            $table->timestamp('processed_at');
            $table->unique(['event_id', 'consumer']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('processed_messages');
        Schema::dropIfExists('outbox_messages');
    }
};
