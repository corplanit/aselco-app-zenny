<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('roles')) {
            return;
        }

        $permission = Permission::query()->updateOrCreate(
            ['code' => 'chat.view'],
            [
                'name' => 'Access customer support chat',
                'module' => 'chat',
                'action' => 'view',
            ]
        );

        $roleCodes = [
            'super_admin',
            'administrator',
            'supervisor',
            'support_manager',
            'csr',
            'department_staff',
            'billing_staff',
            'technical_staff',
            'readonly_staff',
            'content_manager',
            'customer',
        ];

        Role::query()
            ->whereIn('code', $roleCodes)
            ->orWhere('user_type', 'support')
            ->get()
            ->each(function (Role $role) use ($permission) {
                $role->permissions()->syncWithoutDetaching([$permission->id]);
            });
    }

    public function down(): void
    {
        if (! Schema::hasTable('permissions')) {
            return;
        }

        $permission = Permission::query()->where('code', 'chat.view')->first();
        if (! $permission) {
            return;
        }

        $permission->roles()->detach();
        $permission->delete();
    }
};
