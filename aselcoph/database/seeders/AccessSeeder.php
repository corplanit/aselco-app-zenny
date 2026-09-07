<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\TicketUi;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class AccessSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('roles') || ! Schema::hasTable('permissions')) {
            return;
        }

        $this->seedPermissions();
        $roles = $this->seedRoles();
        $this->seedDepartments();
        $this->mapExistingUsers($roles);
    }

    private function seedPermissions(): void
    {
        foreach (config('access.permissions', []) as $row) {
            Permission::query()->updateOrCreate(
                ['code' => $row['code']],
                [
                    'name' => $row['name'],
                    'module' => $row['module'],
                    'action' => $row['action'],
                ],
            );
        }
    }

    /**
     * @return array<string, Role>
     */
    private function seedRoles(): array
    {
        $definitions = [
            'super_admin' => ['name' => 'Super Administrator', 'user_type' => 'support', 'scope' => 'all', 'all' => true],
            'administrator' => ['name' => 'Administrator', 'user_type' => 'support', 'scope' => 'all', 'all' => true],
            'supervisor' => ['name' => 'Supervisor', 'user_type' => 'support', 'scope' => 'department', 'permissions' => [
                'dashboard.view', 'customers.view', 'tickets.view', 'tickets.create', 'tickets.edit',
                'tickets.assign', 'tickets.reassign', 'tickets.escalate', 'tickets.close', 'tickets.export',
                'chat.view', 'notifications.view', 'knowledge.view', 'ai.ticket-analysis.view', 'ai.response-suggestions',
                'reports.view', 'reports.export', 'users.view', 'departments.view', 'audit.view',
                'complaints.view', 'billing.view', 'wallet.view',
            ]],
            'support_manager' => ['name' => 'Support Manager', 'user_type' => 'support', 'scope' => 'department', 'permissions' => [
                'dashboard.view', 'customers.view', 'customers.create', 'tickets.view', 'tickets.create',
                'tickets.edit', 'tickets.assign', 'tickets.reassign', 'tickets.escalate', 'chat.view',
                'notifications.view', 'reports.view', 'users.view',
            ]],
            'csr' => ['name' => 'Customer Service Representative', 'user_type' => 'support', 'scope' => 'all', 'permissions' => [
                'dashboard.view', 'customers.view', 'customers.create', 'customers.edit', 'tickets.view',
                'tickets.create', 'tickets.edit', 'tickets.assign', 'tickets.reassign', 'tickets.close',
                'chat.view', 'notifications.view', 'knowledge.view', 'ai.ticket-analysis.view', 'ai.response-suggestions',
                'complaints.view', 'complaints.create', 'billing.view', 'wallet.view',
            ]],
            'department_staff' => ['name' => 'Department Staff', 'user_type' => 'support', 'scope' => 'department', 'permissions' => [
                'dashboard.view', 'tickets.view', 'tickets.edit', 'tickets.escalate', 'chat.view',
                'notifications.view', 'knowledge.view',
            ]],
            'billing_staff' => ['name' => 'Billing Staff', 'user_type' => 'support', 'scope' => 'department', 'permissions' => [
                'dashboard.view', 'customers.view', 'billing.view', 'wallet.view', 'wallet.transactions.view',
                'tickets.view', 'tickets.edit', 'chat.view', 'notifications.view',
            ]],
            'technical_staff' => ['name' => 'Technical Staff', 'user_type' => 'support', 'scope' => 'department', 'permissions' => [
                'dashboard.view', 'tickets.view', 'tickets.edit', 'tickets.escalate', 'chat.view', 'notifications.view',
            ]],
            'readonly_staff' => ['name' => 'Read-Only Staff', 'user_type' => 'support', 'scope' => 'department', 'permissions' => [
                'dashboard.view', 'tickets.view', 'customers.view', 'reports.view', 'chat.view', 'notifications.view',
            ]],
            'content_manager' => ['name' => 'Content Manager', 'user_type' => 'support', 'scope' => 'all', 'permissions' => [
                'dashboard.view', 'knowledge.view', 'knowledge.create', 'knowledge.edit', 'knowledge.delete',
                'knowledge.publish', 'knowledge.index', 'ai.knowledge.manage', 'chat.view',
            ]],
            'customer' => ['name' => 'Customer', 'user_type' => 'customer', 'scope' => 'own', 'permissions' => [
                'dashboard.view', 'tickets.view', 'tickets.create', 'chat.view', 'notifications.view', 'ai.chat',
                'wallet.view', 'billing.view', 'complaints.create',
            ]],
        ];

        $roles = [];
        $allCodes = Permission::query()->pluck('id', 'code');

        foreach ($definitions as $code => $def) {
            $role = Role::query()->updateOrCreate(
                ['code' => $code],
                [
                    'name' => $def['name'],
                    'description' => $def['name'],
                    'status' => Role::STATUS_ACTIVE,
                    'user_type' => $def['user_type'],
                    'scope' => $def['scope'],
                ],
            );

            $ids = ! empty($def['all'])
                ? $allCodes->values()->all()
                : $allCodes->only($def['permissions'] ?? [])->values()->all();

            $role->permissions()->sync($ids);
            $roles[$code] = $role;
        }

        return $roles;
    }

    private function seedDepartments(): void
    {
        $rows = TicketUi::departmentMeanings();
        $rows['SCADA'] = 'SCADA / Transmission Operations';
        $rows['MAINT'] = 'Maintenance';
        $rows['OTHER'] = 'Other Department / Office';

        $inactive = ['SCADA', 'MAINT', 'OTHER'];

        foreach ($rows as $code => $description) {
            Department::query()->updateOrCreate(
                ['code' => $code],
                [
                    'name' => $code,
                    'description' => $description,
                    'status' => in_array($code, $inactive, true) ? Department::STATUS_INACTIVE : Department::STATUS_ACTIVE,
                    'operating_schedule' => [
                        'day' => [config('access.day_shift_start'), config('access.day_shift_end')],
                        'night' => [config('access.day_shift_end'), config('access.day_shift_start')],
                    ],
                ],
            );
        }
    }

    /**
     * @param  array<string, Role>  $roles
     */
    private function mapExistingUsers(array $roles): void
    {
        $departments = Department::query()->pluck('id', 'code');

        User::query()->orderBy('id')->each(function (User $user) use ($roles, $departments) {
            $roleCode = $this->mapRoleCode($user);
            $role = $roles[$roleCode] ?? $roles['customer'];
            $isSupport = $role->user_type === 'support';

            $payload = [
                'role_id' => $role->id,
                'user_type' => $isSupport ? 'support' : 'customer',
                'account_status' => $user->account_status ?: ($user->email_verified_at ? 'active' : 'pending_activation'),
                'availability_status' => $user->availability_status ?: ($isSupport ? 'offline' : 'offline'),
            ];

            if (filled($user->department_code) && isset($departments[$user->department_code])) {
                $payload['department_id'] = $departments[$user->department_code];
            }

            $user->forceFill($payload)->saveQuietly();

            if (! empty($payload['department_id'])) {
                $user->departments()->syncWithoutDetaching([
                    $payload['department_id'] => ['is_primary' => true],
                ]);
            }

            if ($isSupport && ! $user->staffAvailability) {
                $user->staffAvailability()->create([
                    'status' => $user->availability_status ?: 'offline',
                    'shift' => 'day',
                    'starts_at' => config('access.day_shift_start'),
                    'ends_at' => config('access.day_shift_end'),
                ]);
            }
        });
    }

    private function mapRoleCode(User $user): string
    {
        if (in_array(strtolower((string) $user->email), array_map('strtolower', config('access.super_admin_emails', [])), true)) {
            return 'super_admin';
        }

        return match ((string) ($user->role ?? '')) {
            'Administrator', 'administrator' => 'administrator',
            'Supervisor', 'supervisor' => 'supervisor',
            'Customer Service', 'support' => 'csr',
            'Content Manager' => 'content_manager',
            'User' => 'customer',
            default => filled($user->department_code) ? 'department_staff' : 'customer',
        };
    }
}
