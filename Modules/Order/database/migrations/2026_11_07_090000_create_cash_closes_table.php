<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per business day the till was closed: what the payments added up to and what was counted.
        Schema::create('cash_closes', function (Blueprint $table) {
            $table->id();
            $table->date('business_date')->unique();
            $table->json('totals');
            $table->decimal('expected_cash', 18, 4);
            $table->decimal('counted_cash', 18, 4);
            $table->decimal('difference', 18, 4);
            $table->text('note')->nullable();
            $table->unsignedBigInteger('closed_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_closes');
    }
};
