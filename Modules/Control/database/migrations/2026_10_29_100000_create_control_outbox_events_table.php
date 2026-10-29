<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('control_outbox_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('event_id')->unique();
            $table->string('event_type', 100);
            $table->string('aggregate_type', 100);
            $table->uuid('aggregate_id');
            $table->json('payload');
            $table->timestamp('occurred_at', 6);
            $table->timestamp('pushed_at')->nullable();
            $table->string('rejected_code', 100)->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->string('last_error', 500)->nullable();
            $table->timestamps(6);

            // Pending = neither pushed nor rejected; the pusher scans in id order.
            $table->index(['pushed_at', 'rejected_code', 'id']);
        });

        // Facts that must survive `cache:clear`, e.g. that the first full copy was queued.
        Schema::create('control_state', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->text('value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('control_state');
        Schema::dropIfExists('control_outbox_events');
    }
};
