<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_attempts', function (Blueprint $table) {
            // A refund is its own (negative) payment, so the order's balance and the reports follow.
            $table->timestamp('refunded_at')->nullable()->after('completed_at');
            $table->foreignId('refund_payment_id')->nullable()->after('refunded_at')->constrained('payments')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payment_attempts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('refund_payment_id');
            $table->dropColumn('refunded_at');
        });
    }
};
