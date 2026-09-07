<?php

namespace App\Http\Controllers\Access;

use App\Http\Controllers\Controller;
use App\Models\AccessSetting;
use App\Models\Department;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Ticket;
use App\Models\TicketAssignmentHistory;
use App\Models\User;
use App\Models\UserActivityLog;
use App\Models\UserAvailability;
use App\Services\Access\AccessService;
use App\Services\Access\ActivityLogger;
use App\Support\ListQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AccessOrgWebController extends Controller
{
    public function __construct(
        private AccessService $access,
        private ActivityLogger $activity,
    ) {
    }

    public function departments(Request $request): View
    {
        $this->assert($request, 'departments.view');
        $list = ListQuery::from($request, ['status'], ['code' => 'code', 'name' => 'name', 'id' => 'id'], 'code', 'asc');
        $query = Department::query()->with('head')->withCount('members');
        if ($list['search']) {
            $term = $list['search'];
            $query->where(fn ($q) => $q->where('code', 'like', "%{$term}%")->orWhere('name', 'like', "%{$term}%"));
        }
        if (! empty($list['filters']['status'])) {
            $query->where('status', $list['filters']['status']);
        }
        $departments = ListQuery::applySort($query, $list, ['code' => 'code', 'name' => 'name', 'id' => 'id'])
            ->paginate($list['per_page'])->withQueryString();

        $ticketStats = Ticket::query()
            ->selectRaw("assigned_department, COUNT(*) as open_count, SUM(CASE WHEN sla_due_at < ? AND status NOT IN ('closed','resolved') THEN 1 ELSE 0 END) as sla_count", [now()])
            ->whereNotIn('status', [Ticket::STATUS_CLOSED, Ticket::STATUS_RESOLVED])
            ->groupBy('assigned_department')
            ->get()
            ->keyBy('assigned_department');

        return view('pages.staff.access.departments', compact('departments', 'list', 'ticketStats') + [
            'filters' => array_merge($list['filters'], ['search' => $list['search'], 'sort' => $list['sort'], 'dir' => $list['dir']]),
            'activeFilterCount' => $list['active_filter_count'],
            'heads' => User::query()->where('user_type', 'support')->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function showDepartment(Request $request, Department $department): View
    {
        $this->assert($request, 'departments.view');
        $department->load(['head', 'members.accessRole']);
        $tickets = Ticket::query()->where('assigned_department', $department->code)->latest()->limit(20)->get();

        return view('pages.staff.access.department-show', compact('department', 'tickets'));
    }

    public function storeDepartment(Request $request): RedirectResponse
    {
        $this->assert($request, 'departments.create');
        $data = $request->validate([
            'code' => 'required|string|max:40|unique:departments,code',
            'name' => 'required|string|max:120',
            'description' => 'nullable|string',
            'contact' => 'nullable|string|max:120',
            'head_user_id' => 'nullable|integer|exists:users,id',
            'status' => 'nullable|in:active,inactive',
        ]);
        $data['created_by'] = $request->user()->id;
        $data['updated_by'] = $request->user()->id;
        $department = Department::query()->create($data);
        $this->activity->record('department.created', $request->user(), $department);

        return back()->with('success', 'Department created.');
    }

    public function updateDepartment(Request $request, Department $department): RedirectResponse
    {
        $this->assert($request, 'departments.edit');
        $data = $request->validate([
            'code' => 'required|string|max:40|unique:departments,code,'.$department->id,
            'name' => 'required|string|max:120',
            'description' => 'nullable|string',
            'contact' => 'nullable|string|max:120',
            'head_user_id' => 'nullable|integer|exists:users,id',
            'status' => 'nullable|in:active,inactive',
        ]);
        $data['updated_by'] = $request->user()->id;
        $department->update($data);
        $this->activity->record('department.updated', $request->user(), $department);

        return back()->with('success', 'Department updated.');
    }

    public function roles(Request $request): View
    {
        $this->assert($request, 'roles.view');
        $roles = Role::query()->withCount('users')->orderBy('name')->get();

        return view('pages.staff.access.roles', compact('roles'));
    }

    public function storeRole(Request $request): RedirectResponse
    {
        $this->assert($request, 'roles.create');
        Role::query()->create($request->validate([
            'name' => 'required|string|max:80',
            'code' => 'required|string|max:40|unique:roles,code',
            'description' => 'nullable|string',
            'user_type' => 'required|in:customer,support',
            'scope' => 'required|in:all,department,assigned,own',
        ]));

        return back()->with('success', 'Role created.');
    }

    public function updateRole(Request $request, Role $role): RedirectResponse
    {
        $this->assert($request, 'roles.edit');
        $role->update($request->validate([
            'name' => 'required|string|max:80',
            'description' => 'nullable|string',
            'status' => 'nullable|in:active,inactive',
            'scope' => 'nullable|in:all,department,assigned,own',
        ]));

        return back()->with('success', 'Role updated.');
    }

    public function permissions(Request $request): View
    {
        $this->assert($request, 'permissions.view');
        $roles = Role::query()->with('permissions')->orderBy('name')->get();
        $target = $roles->firstWhere('id', (int) $request->query('role_id')) ?? $roles->first();
        $matrix = $target ? $this->access->matrixForRole($target) : [];

        return view('pages.staff.access.permissions', [
            'roles' => $roles,
            'target' => $target,
            'matrix' => $matrix,
            'modules' => config('access.modules', []),
        ]);
    }

    public function updateRolePermissions(Request $request, Role $role): RedirectResponse
    {
        $this->assert($request, 'users.permissions.manage');
        $granted = [];
        foreach ((array) $request->input('cells', []) as $module => $actions) {
            foreach ((array) $actions as $action => $on) {
                if ($on) {
                    $granted = array_merge($granted, $this->access->permissionsForModuleAction($module, $action)->pluck('id')->all());
                }
            }
        }
        $role->permissions()->sync(array_unique($granted));
        $this->activity->record('role.permissions', $request->user(), $role);

        return back()->with('success', 'Permission matrix saved.');
    }

    public function sessions(Request $request): View
    {
        $this->assert($request, 'sessions.view');
        $sessions = DB::table('sessions')
            ->leftJoin('users', 'users.id', '=', 'sessions.user_id')
            ->select('sessions.*', 'users.name', 'users.email', 'users.user_type')
            ->whereNotNull('sessions.user_id')
            ->orderByDesc('sessions.last_activity')
            ->paginate(25)
            ->withQueryString();

        return view('pages.staff.access.sessions', compact('sessions'));
    }

    public function revokeSession(Request $request, string $id): RedirectResponse
    {
        $this->assert($request, 'sessions.revoke');
        DB::table('sessions')->where('id', $id)->delete();
        $this->activity->record('session.revoked', $request->user(), null, null, ['session_id' => $id]);

        return back()->with('success', 'Session revoked.');
    }

    public function revokeUserSessions(Request $request, User $user): RedirectResponse
    {
        $this->assert($request, 'sessions.revoke');
        DB::table('sessions')->where('user_id', $user->id)->delete();
        $this->activity->record('session.revoked_all', $request->user(), $user);

        return back()->with('success', 'All sessions revoked.');
    }

    public function activity(Request $request): View
    {
        $this->assert($request, 'audit.view');
        $list = ListQuery::from($request, ['action'], ['created_at' => 'created_at', 'id' => 'id'], 'created_at', 'desc');
        $query = UserActivityLog::query()->with('user');
        if ($list['search']) {
            $term = $list['search'];
            $query->where(fn ($q) => $q->where('action', 'like', "%{$term}%")->orWhere('ip_address', 'like', "%{$term}%"));
        }
        if (! empty($list['filters']['action'])) {
            $query->where('action', $list['filters']['action']);
        }
        $logs = $query->orderByDesc('created_at')->paginate($list['per_page'])->withQueryString();

        return view('pages.staff.access.activity', [
            'logs' => $logs,
            'list' => $list,
            'filters' => array_merge($list['filters'], ['search' => $list['search']]),
            'activeFilterCount' => $list['active_filter_count'],
        ]);
    }

    public function availability(Request $request): View
    {
        $this->assert($request, 'users.view');
        $staff = User::query()->where('user_type', 'support')
            ->with('staffAvailability', 'accessDepartment', 'schedules')
            ->orderBy('name')
            ->paginate(25);

        return view('pages.staff.access.availability', compact('staff'));
    }

    public function updateAvailability(Request $request, User $user): RedirectResponse
    {
        $this->assert($request, 'users.edit');
        $data = $request->validate([
            'status' => 'required|string',
            'shift' => 'nullable|string',
            'starts_at' => 'nullable',
            'ends_at' => 'nullable',
            'on_leave' => 'nullable|boolean',
            'note' => 'nullable|string',
        ]);
        UserAvailability::query()->updateOrCreate(['user_id' => $user->id], $data);
        $user->forceFill(['availability_status' => $data['status']])->save();

        return back()->with('success', 'Availability updated.');
    }

    public function assignments(Request $request): View
    {
        $this->assert($request, 'tickets.view');
        $list = ListQuery::from($request, ['assigned_department', 'assigned_to', 'status', 'priority'], ['id' => 'id'], 'id', 'desc');
        $query = Ticket::query()->with(['category', 'assignee', 'customer']);
        if ($list['search']) {
            $term = $list['search'];
            $query->where(fn ($q) => $q->where('ticket_no', 'like', "%{$term}%")->orWhere('description', 'like', "%{$term}%"));
        }
        foreach (['assigned_department', 'assigned_to', 'status', 'priority'] as $key) {
            if (! empty($list['filters'][$key])) {
                $query->where($key, $list['filters'][$key]);
            }
        }
        $tickets = $query->latest()->paginate($list['per_page'])->withQueryString();

        return view('pages.staff.access.assignments', [
            'tickets' => $tickets,
            'list' => $list,
            'filters' => array_merge($list['filters'], ['search' => $list['search']]),
            'activeFilterCount' => $list['active_filter_count'],
            'departments' => Department::query()->where('status', 'active')->orderBy('code')->get(),
            'assignees' => User::query()->where('user_type', 'support')->orderBy('name')->get(['id', 'name', 'department_code']),
        ]);
    }

    public function assignmentHistory(Request $request, int $ticket): View
    {
        $this->assert($request, 'tickets.view');
        $history = TicketAssignmentHistory::query()
            ->with(['previousAssignee', 'newAssignee', 'assignedBy'])
            ->where('ticket_id', $ticket)
            ->latest('created_at')
            ->get();

        return view('pages.staff.access.assignment-history', [
            'ticket' => Ticket::query()->findOrFail($ticket),
            'history' => $history,
        ]);
    }

    public function settings(Request $request): View
    {
        $this->assert($request, 'settings.view');
        $keys = [
            'default_role', 'default_department', 'assignment_strategy', 'ai_assisted_assignment',
            'max_workload', 'availability_blocks_assignment', 'lockout_attempts', 'lockout_minutes',
            'session_timeout_minutes', 'password_min_length', 'password_require_mixed', 'mfa_required_for_staff',
            'day_shift_start', 'day_shift_end',
        ];
        $settings = [];
        foreach ($keys as $key) {
            $settings[$key] = $this->access->setting($key);
        }

        return view('pages.staff.access.settings', [
            'settings' => $settings,
            'roles' => Role::query()->orderBy('name')->get(),
            'departments' => Department::query()->orderBy('code')->get(),
        ]);
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $this->assert($request, 'settings.manage');
        foreach ($request->except(['_token']) as $key => $value) {
            AccessSetting::putValue((string) $key, $value, $request->user()->id);
        }
        $this->activity->record('settings.updated', $request->user());

        return back()->with('success', 'Settings saved.');
    }

    public function reports(Request $request): View|StreamedResponse
    {
        $this->assert($request, 'reports.view');
        $type = (string) $request->query('type', 'active_users');
        $rows = $this->reportRows($type);

        if ($request->query('export')) {
            $this->assert($request, 'reports.export');

            return response()->streamDownload(function () use ($rows) {
                $out = fopen('php://output', 'w');
                if ($rows !== []) {
                    fputcsv($out, array_keys($rows[0]));
                    foreach ($rows as $row) {
                        fputcsv($out, $row);
                    }
                }
                fclose($out);
            }, $type.'.csv');
        }

        return view('pages.staff.access.reports', compact('type', 'rows'));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function reportRows(string $type): array
    {
        return match ($type) {
            'inactive_users' => User::query()->where('account_status', '!=', User::STATUS_ACTIVE)->get(['id', 'name', 'email', 'account_status', 'user_type'])->toArray(),
            'staff_by_department' => User::query()->where('user_type', 'support')->selectRaw('department_code, COUNT(*) as staff')->groupBy('department_code')->get()->toArray(),
            'tickets_per_account' => Ticket::query()->selectRaw('assigned_to, COUNT(*) as tickets')->whereNotNull('assigned_to')->groupBy('assigned_to')->get()->toArray(),
            'tickets_per_department' => Ticket::query()->selectRaw('assigned_department, COUNT(*) as tickets')->groupBy('assigned_department')->get()->toArray(),
            'login_activity' => UserActivityLog::query()->where('action', 'login')->latest('created_at')->limit(200)->get(['user_id', 'ip_address', 'created_at'])->toArray(),
            'assignment_methods' => TicketAssignmentHistory::query()->selectRaw('method, COUNT(*) as total')->groupBy('method')->get()->toArray(),
            default => User::query()->where('account_status', User::STATUS_ACTIVE)->get(['id', 'name', 'email', 'user_type', 'department_code'])->toArray(),
        };
    }

    private function assert(Request $request, string $permission): void
    {
        if (! $this->access->allows($request->user(), $permission)) {
            abort(403);
        }
    }
}
