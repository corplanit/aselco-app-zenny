<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Ticket;
use App\Models\TicketAssignmentHistory;
use App\Models\User;
use App\Services\Access\AccessService;
use App\Services\Access\UserManagementService;
use App\Services\TicketService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccessAdminApiController extends Controller
{
    public function __construct(
        private AccessService $access,
        private UserManagementService $users,
        private TicketService $tickets,
    ) {
    }

    public function users(Request $request): JsonResponse
    {
        $this->assert($request, 'users.view');
        $query = User::query()->with(['accessRole', 'accessDepartment']);
        if ($request->filled('user_type')) {
            $query->where('user_type', $request->query('user_type'));
        }

        return response()->json($query->orderBy('name')->paginate((int) $request->query('per_page', 25)));
    }

    public function storeUser(Request $request): JsonResponse
    {
        $this->assert($request, 'users.create');
        $user = $this->users->create($request->user(), $request->validate([
            'name' => 'required|string',
            'email' => 'required|email|unique:users,email',
            'role_id' => 'nullable|integer|exists:roles,id',
            'department_id' => 'nullable|integer|exists:departments,id',
            'user_type' => 'nullable|in:customer,support',
        ]));

        return response()->json(['data' => $user], 201);
    }

    public function showUser(Request $request, User $user): JsonResponse
    {
        $this->assert($request, 'users.view');

        return response()->json(['data' => $user->load(['accessRole', 'accessDepartment'])]);
    }

    public function updateUser(Request $request, User $user): JsonResponse
    {
        $this->assert($request, 'users.edit');

        return response()->json(['data' => $this->users->update($request->user(), $user, $request->all())]);
    }

    public function destroyUser(Request $request, User $user): JsonResponse
    {
        $this->assert($request, 'users.suspend');
        $this->users->setStatus($request->user(), $user, User::STATUS_INACTIVE);

        return response()->json(['data' => $user->fresh()]);
    }

    public function departments(): JsonResponse
    {
        $this->assert(request(), 'departments.view');

        return response()->json(Department::query()->orderBy('code')->paginate(50));
    }

    public function storeDepartment(Request $request): JsonResponse
    {
        $this->assert($request, 'departments.create');
        $department = Department::query()->create($request->validate([
            'code' => 'required|string|unique:departments,code',
            'name' => 'required|string',
            'description' => 'nullable|string',
            'status' => 'nullable|in:active,inactive',
        ]));

        return response()->json(['data' => $department], 201);
    }

    public function showDepartment(Request $request, Department $department): JsonResponse
    {
        $this->assert($request, 'departments.view');

        return response()->json(['data' => $department->load('members')]);
    }

    public function updateDepartment(Request $request, Department $department): JsonResponse
    {
        $this->assert($request, 'departments.edit');
        $department->update($request->only(['name', 'description', 'status', 'contact', 'head_user_id']));

        return response()->json(['data' => $department]);
    }

    public function roles(): JsonResponse
    {
        $this->assert(request(), 'roles.view');

        return response()->json(Role::query()->with('permissions')->orderBy('name')->get());
    }

    public function storeRole(Request $request): JsonResponse
    {
        $this->assert($request, 'roles.create');

        return response()->json(['data' => Role::query()->create($request->validate([
            'name' => 'required|string',
            'code' => 'required|string|unique:roles,code',
            'user_type' => 'required|in:customer,support',
            'scope' => 'required|string',
        ]))], 201);
    }

    public function updateRole(Request $request, Role $role): JsonResponse
    {
        $this->assert($request, 'roles.edit');
        $role->update($request->only(['name', 'description', 'status', 'scope']));

        return response()->json(['data' => $role]);
    }

    public function permissions(): JsonResponse
    {
        $this->assert(request(), 'permissions.view');

        return response()->json(Permission::query()->orderBy('module')->orderBy('code')->get());
    }

    public function rolePermissions(Role $role): JsonResponse
    {
        $this->assert(request(), 'permissions.view');

        return response()->json(['data' => $role->permissions()->pluck('code')]);
    }

    public function updateRolePermissions(Request $request, Role $role): JsonResponse
    {
        $this->assert($request, 'users.permissions.manage');
        $codes = $request->validate(['permissions' => 'required|array'])['permissions'];
        $ids = Permission::query()->whereIn('code', $codes)->pluck('id');
        $role->permissions()->sync($ids);

        return response()->json(['data' => $role->permissions()->pluck('code')]);
    }

    public function updateUserPermissions(Request $request, User $user): JsonResponse
    {
        $this->assert($request, 'users.permissions.manage');
        $this->users->syncUserPermissions($request->user(), $user, $request->validate(['overrides' => 'required|array'])['overrides']);

        return response()->json(['data' => $this->access->effectiveCodes($user)]);
    }

    public function assignment(Request $request, int $id): JsonResponse
    {
        $this->assert($request, 'tickets.view');
        $ticket = $this->tickets->showAdmin($request->user(), $id);

        return response()->json([
            'assigned_to' => $ticket->assigned_to,
            'assigned_department' => $ticket->assigned_department,
        ]);
    }

    public function assign(Request $request, int $id): JsonResponse
    {
        $this->assert($request, 'tickets.assign');
        $validated = $request->validate([
            'assigned_department' => 'required|string|max:40',
            'assigned_to' => 'nullable|integer|exists:users,id',
            'remarks' => 'nullable|string',
        ]);

        return response()->json($this->tickets->reassign($request->user(), $id, $validated));
    }

    public function reassign(Request $request, int $id): JsonResponse
    {
        return $this->assign($request, $id);
    }

    public function assignmentHistory(Request $request, int $id): JsonResponse
    {
        $this->assert($request, 'tickets.view');
        $this->tickets->showAdmin($request->user(), $id);

        return response()->json(
            TicketAssignmentHistory::query()->where('ticket_id', $id)->latest('created_at')->get()
        );
    }

    public function supportTickets(Request $request): JsonResponse
    {
        $this->assert($request, 'tickets.view');

        return response()->json(
            Ticket::query()->where('assigned_to', $request->user()->id)->latest()->paginate(25)
        );
    }

    public function supportDashboard(Request $request): JsonResponse
    {
        $this->assert($request, 'dashboard.view');
        $mine = Ticket::query()->where('assigned_to', $request->user()->id);

        return response()->json([
            'open' => (clone $mine)->whereNotIn('status', [Ticket::STATUS_CLOSED, Ticket::STATUS_RESOLVED])->count(),
            'escalated' => (clone $mine)->where('status', Ticket::STATUS_ESCALATED)->count(),
        ]);
    }

    public function supportProfile(Request $request): JsonResponse
    {
        return response()->json(['data' => $request->user()->load(['accessRole', 'accessDepartment'])]);
    }

    private function assert(Request $request, string $permission): void
    {
        if (! $this->access->allows($request->user(), $permission)) {
            abort(403);
        }
    }
}
