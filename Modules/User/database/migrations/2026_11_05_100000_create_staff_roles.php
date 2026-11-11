<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Modules\User\Enums\UserRole;

return new class extends Migration
{
    public function up(): void
    {
        foreach (UserRole::cases() as $role) {
            DB::table('roles')->insertOrIgnore([
                'name' => $role->value,
                'guard_name' => 'web',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('roles')->whereIn('name', array_column(UserRole::cases(), 'value'))->delete();
    }
};
