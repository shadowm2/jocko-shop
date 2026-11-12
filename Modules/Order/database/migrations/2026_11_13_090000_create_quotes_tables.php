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
        Schema::create('quotes', function (Blueprint $table) {
            $table->id();
            $table->slug();
            $table->string('quote_number')->unique();
            $table->foreignId('user_id')->constrained();
            $table->foreignId('user_car_id')->constrained('user_car');
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->string('status')->index();
            $table->date('valid_until')->nullable();
            $table->text('description')->nullable();
            // The order this quote became, once the customer said yes.
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('quote_items', function (Blueprint $table) {
            $table->id();
            $table->slug();
            $table->foreignId('quote_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained()->restrictOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained('units')->restrictOnDelete();
            $table->foreignId('mechanic_id')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('quantity', 18, 4);
            $table->decimal('unit_price', 18, 4);
            $table->decimal('total', 18, 4);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['quote_id', 'item_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quote_items');
        Schema::dropIfExists('quotes');
    }
};
