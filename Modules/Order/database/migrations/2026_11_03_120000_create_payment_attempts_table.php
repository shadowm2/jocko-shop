<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A try at collecting money for an order, through an online gateway or a
        // card terminal. Only when it succeeds does it become a payment.
        Schema::create('payment_attempts', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('kind', 10);
            $table->string('driver', 30);
            $table->decimal('amount', 18, 4);
            $table->string('currency', 3);
            $table->string('status', 20)->index();
            $table->string('reference')->nullable()->index();
            $table->text('pay_url')->nullable();
            $table->string('result_reference')->nullable();
            $table->string('card_pan', 32)->nullable();
            $table->text('failure')->nullable();
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('expires_at');
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'expires_at']);
            $table->index(['order_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_attempts');
    }
};
