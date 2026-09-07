<?php

namespace App\Services\Access;

use App\Models\AccessSetting;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class AccessService
{
    public const SCOPE_ALL = 'all';
    public const SCOPE_DEPARTMENT = 'department';
    public const SCOPE_ASSIGNED = 'assigned';
    public const SCOPE_OWN = 'own';

    /** @var array<int, array<int, string>> */
    private array $effectiveCache = [];

    public function catalogReady(): bool
    {
        try {
            return Schema::hasTable('permissions') && Permission::query()->exists();
        } catch (\Throwable) {
            return false;
        }
    }

    public function isAccountUsable(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        $status = (string) ($user->account_status ?? 'active');
        if (in_array($status, ['inactive', 'suspended', 'locked'], true)) {
            return false;
        }

        if ($user->locked_until && $user->locked_until->isFuture()) {
            return false;
        }

        return true;
    }

    public function isSuperAdmin(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        if (in_array(strtolower((string) $user->email), array_map('strtolower', config('access.super_admin_emails', [])), true)) {
            return true;
        }

        $roleCode = $user->accessRole?->code ?? $this->legacyRoleCode($user);
        if (in_array($roleCode, config('access.super_admin_roles', []), true)) {
            return true;
        }

        return in_array((string) ($user->role ?? ''), config('tickets.admin_roles', []), true);
    }

    public function allows(?User $user, string $permission): bool
    {
        if ($user === null) {
            return false;
        }

        if (! $this->isAccountUsable($user) && ! in_array($permission, ['users.view'], true)) {
            return $this->isSuperAdmin($user);
        }

        if ($this->isSuperAdmin($user)) {
            return true;
        }

        if (! $this->catalogReady() || ! $user->role_id) {
            return $this->legacyAllows($user, $permission);
        }

        return in_array($permission, $this->effectiveCodes($user), true);
    }

    /**
     * @return list<string>
     */
    public function effectiveCodes(User $user): array
    {
        if (isset($this->effectiveCache[$user->id])) {
            return $this->effectiveCache[$user->id];
        }

        if ($this->isSuperAdmin($user)) {
            return $this->effectiveCache[$user->id] = Permission::query()->pluck('code')->all();
        }

        $granted = collect();

        if ($user->department_id) {
            $granted = $granted->merge(
                $user->accessDepartment?->permissions?->pluck('code') ?? collect()
            );
        }

        if ($user->role_id) {
            $granted = $granted->merge(
                $user->accessRole?->permissions?->pluck('code') ?? collect()
            );
        }

        $overrides = $user->permissionOverrides()
            ->with('permission')
            ->get();

        foreach ($overrides as $override) {
            $code = $override->permission?->code;
            if (! $code) {
                continue;
            }
            if ($override->allowed) {
                $granted->push($code);
            } else {
                $granted = $granted->reject(fn ($item) => $item === $code);
            }
        }

        return $this->effectiveCache[$user->id] = $granted->unique()->values()->all();
    }

    public function scopeFor(?User $user): string
    {
        if ($user === null) {
            return self::SCOPE_OWN;
        }
        if ($this->isSuperAdmin($user) || $this->legacyWideView($user)) {
            return self::SCOPE_ALL;
        }
        if (filled($user->department_code) || $user->department_id) {
            return self::SCOPE_DEPARTMENT;
        }
        if ($this->isSupport($user)) {
            return self::SCOPE_ASSIGNED;
        }

        return self::SCOPE_OWN;
    }

    public function isSupport(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        if (($user->user_type ?? null) === 'support') {
            return true;
        }

        return in_array((string) ($user->role ?? ''), [
            'Administrator', 'administrator', 'Customer Service', 'staff',
            'support', 'Supervisor', 'supervisor', 'Content Manager',
        ], true) || filled($user->department_code) || ($user->user_type ?? null) === 'support';
    }

    public function isCustomer(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        return ! $this->isSupport($user);
    }

    public function setting(string $key, mixed $default = null): mixed
    {
        try {
            return AccessSetting::getValue($key, $default);
        } catch (\Throwable) {
            return config('access.'.$key, $default);
        }
    }

    /**
     * @return array<string, list<string>>
     */
    public function matrixForRole(Role $role): array
    {
        $codes = $role->permissions()->pluck('code')->all();

        return $this->codesToMatrix($codes);
    }

    /**
     * @param  list<string>  $codes
     * @return array<string, array<string, bool>>
     */
    public function codesToMatrix(array $codes): array
    {
        $matrix = [];
        foreach (config('access.modules', []) as $module => $actions) {
            foreach ($actions as $action) {
                $matrix[$module][$action] = $this->moduleActionGranted($codes, $module, $action);
            }
        }

        return $matrix;
    }

    /**
     * @param  list<string>  $codes
     */
    public function moduleActionGranted(array $codes, string $module, string $action): bool
    {
        foreach (config('access.permissions', []) as $row) {
            if ($row['module'] === $module && $row['action'] === $action && in_array($row['code'], $codes, true)) {
                return true;
            }
        }

        return in_array($module.'.'.$action, $codes, true);
    }

    /**
     * @return Collection<int, Permission>
     */
    public function permissionsForModuleAction(string $module, string $action): Collection
    {
        return Permission::query()
            ->where('module', $module)
            ->where('action', $action)
            ->get();
    }

    public function ticketVisible(User $actor, Ticket $ticket): bool
    {
        if (! $this->allows($actor, 'tickets.view')) {
            return false;
        }

        return match ($this->scopeFor($actor)) {
            self::SCOPE_ALL => true,
            self::SCOPE_DEPARTMENT => (int) $ticket->assigned_to === (int) $actor->id
                || ($actor->canOpenUnassignedTickets()
                    && $ticket->assigned_department === $actor->department_code),
            self::SCOPE_ASSIGNED => (int) $ticket->assigned_to === (int) $actor->id,
            default => (int) $ticket->customer_id === (int) $actor->id,
        };
    }

    public function customerVisible(User $actor, User $customer): bool
    {
        if ($this->isSuperAdmin($actor) || $this->allows($actor, 'customers.view') && $this->scopeFor($actor) === self::SCOPE_ALL) {
            return true;
        }

        if (! $this->allows($actor, 'customers.view')) {
            return false;
        }

        return Ticket::query()
            ->where('customer_id', $customer->id)
            ->where(function ($q) use ($actor) {
                $q->where('assigned_to', $actor->id);
                if (filled($actor->department_code)) {
                    $q->orWhere('assigned_department', $actor->department_code);
                }
            })
            ->exists();
    }

    private function legacyWideView(User $user): bool
    {
        $role = (string) ($user->role ?? '');
        $code = $user->accessRole?->code;

        return $this->isSuperAdmin($user)
            || in_array($code, ['super_admin', 'administrator', 'supervisor', 'csr', 'support_manager'], true)
            || in_array($role, array_merge(
                config('tickets.admin_roles', []),
                config('tickets.supervisor_roles', []),
                config('tickets.csr_roles', []),
            ), true);
    }

    private function legacyRoleCode(User $user): string
    {
        return match ((string) ($user->role ?? '')) {
            'Administrator', 'administrator' => 'administrator',
            'Supervisor', 'supervisor' => 'supervisor',
            'Customer Service', 'support' => 'csr',
            'Content Manager' => 'content_manager',
            'User' => 'customer',
            default => strtolower((string) ($user->role ?? 'customer')),
        };
    }

    private function legacyAllows(User $user, string $permission): bool
    {
        $admin = $this->isSuperAdmin($user);
        $supervisor = $this->legacyWideView($user) && ! $admin && in_array((string) ($user->role ?? ''), config('tickets.supervisor_roles', []), true);
        $csr = in_array((string) ($user->role ?? ''), config('tickets.csr_roles', []), true);
        $tickets = $admin || $supervisor || $csr || filled($user->department_code);
        $wallet = in_array((string) ($user->role ?? ''), config('ast.load_wallet_roles', []), true);

        return match (true) {
            str_starts_with($permission, 'users.') => $admin,
            str_starts_with($permission, 'departments.') => $admin,
            str_starts_with($permission, 'roles.') => $admin,
            str_starts_with($permission, 'permissions.') => $admin,
            str_starts_with($permission, 'settings.') => $admin,
            str_starts_with($permission, 'sessions.') => $admin,
            str_starts_with($permission, 'audit.') => $admin || $supervisor,
            $permission === 'tickets.view' => $tickets,
            $permission === 'tickets.create' => $admin || $supervisor || $csr,
            $permission === 'tickets.edit' => $tickets,
            in_array($permission, ['tickets.assign', 'tickets.reassign'], true) => $admin || $supervisor || $csr,
            $permission === 'tickets.escalate' => $tickets,
            $permission === 'tickets.close' => $admin || $supervisor || $csr,
            $permission === 'tickets.export' => $admin || $supervisor,
            str_starts_with($permission, 'knowledge.') => $tickets,
            str_starts_with($permission, 'ai.') => $tickets,
            $permission === 'wallet.load' => $wallet,
            $permission === 'wallet.approve' => $wallet,
            str_starts_with($permission, 'wallet.') => $tickets || $wallet || $this->isSupport($user),
            $permission === 'customers.view' => $tickets || $this->isSupport($user),
            $permission === 'customers.create' => $admin || $csr,
            $permission === 'customers.edit' => $admin || $csr,
            $permission === 'reports.view', $permission === 'reports.export' => $admin || $supervisor,
            $permission === 'dashboard.view' => true,
            $permission === 'notifications.view' => true,
            $permission === 'billing.view' => $admin || $csr,
            $permission === 'complaints.view', $permission === 'complaints.create' => true,
            default => $admin,
        };
    }
}
