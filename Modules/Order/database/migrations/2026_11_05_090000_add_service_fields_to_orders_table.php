<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // What the car's odometer read at this visit, and when it should come back.
            $table->unsignedInteger('odometer')->nullable()->after('description');
            $table->date('next_service_at')->nullable()->after('odometer')->index();
            $table->timestamp('service_reminded_at')->nullable()->after('next_service_at');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['next_service_at']);
            $table->dropColumn(['odometer', 'next_service_at', 'service_reminded_at']);
        });
    }
};
