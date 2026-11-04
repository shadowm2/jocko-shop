<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            // Only cheques have these. The status is null for every other payment.
            $table->string('cheque_number', 40)->nullable()->after('description');
            $table->string('cheque_bank', 60)->nullable()->after('cheque_number');
            $table->date('cheque_due_at')->nullable()->after('cheque_bank');
            $table->string('cheque_status', 20)->nullable()->after('cheque_due_at');

            $table->index(['cheque_status', 'cheque_due_at']);
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex(['cheque_status', 'cheque_due_at']);
            $table->dropColumn(['cheque_number', 'cheque_bank', 'cheque_due_at', 'cheque_status']);
        });
    }
};
