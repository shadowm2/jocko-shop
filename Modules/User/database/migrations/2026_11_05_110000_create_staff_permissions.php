<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Modules\User\Enums\UserPermission;
use Modules\User\Enums\UserRole;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        foreach (UserPermission::values() as $name) {
            DB::table('permissions')->insertOrIgnore(['name' => $name, 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()]);
        }

        $manager = DB::table('roles')->where('name', UserRole::Manager->value)->where('guard_name', 'web')->value('id');

        foreach (DB::table('permissions')->whereIn('name', UserPermission::values())->pluck('id') as $permission) {
            DB::table('role_has_permissions')->insertOrIgnore(['permission_id' => $permission, 'role_id' => $manager]);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        DB::table('permissions')->whereIn('name', UserPermission::values())->delete();
    }
};
