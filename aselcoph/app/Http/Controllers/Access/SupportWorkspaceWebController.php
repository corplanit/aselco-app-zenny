<?php

namespace App\Http\Controllers\Access;

use App\Http\Controllers\Controller;
use App\Models\AppNotification;
use App\Models\Ticket;
use App\Models\TicketAssignmentHistory;
use App\Models\User;
use App\Services\Access\AccessService;
use App\Services\TicketService;
use App\Support\ListQuery;
use App\Support\TicketUi;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SupportWorkspaceWebController extends Controller
{
    public function __construct(
        private AccessService $access,
        private TicketService $tickets,
    ) {
    }

    public function dashboard(Request $request): View
    {
        $this->assert($request, 'dashboard.view');
        $user = $request->user();
        $mine = Ticket::query()->where('assigned_to', $user->id);
        $open = (clone $mine)->whereNotIn('status', [Ticket::STATUS_CLOSED, Ticket::STATUS_RESOLVED]);

        $stats = [
            'open' => (clone $open)->count(),
            'new_assignments' => (clone $mine)->where('status', Ticket::STATUS_ASSIGNED)->count(),
            'near_sla' => (clone $open)->whereNotNull('sla_due_at')->whereBetween('sla_due_at', [now(), now()->addHours(4)])->count(),
            'breached' => (clone $open)->whereNotNull('sla_due_at')->where('sla_due_at', '<', now())->count(),
            'awaiting' => (clone $mine)->where('status', Ticket::STATUS_AWAITING_FEEDBACK)->count(),
            'escalated' => (clone $mine)->where('status', Ticket::STATUS_ESCALATED)->count(),
            'completed_today' => (clone $mine)->whereIn('status', [Ticket::STATUS_RESOLVED, Ticket::STATUS_CLOSED])->whereDate('updated_at', now()->toDateString())->count(),
        ];

        $notifications = AppNotification::query()->where('user_id', $user->id)->latest()->limit(8)->get();

        return view('pages.staff.access.support-dashboard', compact('stats', 'notifications'));
    }

    public function supervisor(Request $request): View
    {
        $this->assert($request, 'tickets.view');
        $user = $request->user();
        if (! $user->isTicketSupervisor() && ! $this->access->isSuperAdmin($user)) {
            abort(403);
        }

        $dept = $user->department_code;
        $query = Ticket::query();
        if ($dept && ! $this->access->isSuperAdmin($user)) {
            $query->where('assigned_department', $dept);
        }

        $stats = [
            'volume' => (clone $query)->count(),
            'unassigned' => (clone $query)->whereNull('assigned_to')->whereNotIn('status', [Ticket::STATUS_CLOSED])->count(),
            'escalated' => (clone $query)->where('status', Ticket::STATUS_ESCALATED)->count(),
            'near_sla' => (clone $query)->whereNotNull('sla_due_at')->whereBetween('sla_due_at', [now(), now()->addHours(4)])->whereNotIn('status', [Ticket::STATUS_CLOSED, Ticket::STATUS_RESOLVED])->count(),
            'breached' => (clone $query)->whereNotNull('sla_due_at')->where('sla_due_at', '<', now())->whereNotIn('status', [Ticket::STATUS_CLOSED, Ticket::STATUS_RESOLVED])->count(),
        ];

        $staff = User::query()
            ->where('user_type', 'support')
            ->when($dept && ! $this->access->isSuperAdmin($user), fn ($q) => $q->where('department_code', $dept))
            ->withCount([
                'assignedTickets as open_tickets' => fn ($q) => $q->whereNotIn('status', [Ticket::STATUS_CLOSED, Ticket::STATUS_RESOLVED]),
            ])
            ->orderBy('name')
            ->get();

        $methods = TicketAssignmentHistory::query()
            ->selectRaw('method, COUNT(*) as total')
            ->groupBy('method')
            ->pluck('total', 'method');

        return view('pages.staff.access.supervisor-dashboard', compact('stats', 'staff', 'methods'));
    }

    public function myTickets(Request $request): View
    {
        $this->assert($request, 'tickets.view');

        return $this->ticketList($request, Ticket::query()->where('assigned_to', $request->user()->id), 'Assigned Ticket');
    }

    public function departmentQueue(Request $request): View
    {
        $this->assert($request, 'tickets.view');
        $user = $request->user();
        $query = $this->departmentTicketQuery($user);
        $open = (clone $query)->whereNotIn('status', [Ticket::STATUS_CLOSED, Ticket::STATUS_RESOLVED]);

        $stats = [
            'open' => (clone $open)->count(),
            'unassigned' => (clone $query)->whereNull('assigned_to')->whereNotIn('status', [Ticket::STATUS_CLOSED, Ticket::STATUS_RESOLVED])->count(),
            'assigned' => (clone $query)->where('status', Ticket::STATUS_ASSIGNED)->count(),
            'in_progress' => (clone $query)->where('status', Ticket::STATUS_IN_PROGRESS)->count(),
            'awaiting' => (clone $query)->where('status', Ticket::STATUS_AWAITING_FEEDBACK)->count(),
            'escalated' => (clone $query)->where('status', Ticket::STATUS_ESCALATED)->count(),
            'overdue' => (clone $open)->whereNotNull('sla_due_at')->where('sla_due_at', '<', now())->count(),
        ];

        $recent = (clone $open)
            ->with(['category', 'customer', 'assignee'])
            ->latest()
            ->limit(10)
            ->get();

        $staff = collect();
        if ($user->canOpenUnassignedTickets()) {
            $dept = $user->department_code;
            $staff = User::query()
                ->where('user_type', 'support')
                ->when($dept && ! $this->access->isSuperAdmin($user), fn ($q) => $q->where('department_code', $dept))
                ->withCount([
                    'assignedTickets as open_tickets' => fn ($q) => $q->whereNotIn('status', [Ticket::STATUS_CLOSED, Ticket::STATUS_RESOLVED]),
                ])
                ->orderBy('name')
                ->get();
        }

        $filters = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);
        $analytics = $this->tickets->staffAnalytics($user, $filters);
        $departments = array_keys(TicketUi::departmentMeanings());

        return view('pages.staff.access.ticket-dashboard', [
            'stats' => $stats,
            'recent' => $recent,
            'staff' => $staff,
            'scope' => $this->departmentScopeLabel($user),
            'analytics' => $analytics,
            'filters' => $filters,
            'departments' => $departments,
        ]);
    }

    public function sla(Request $request): View
    {
        $this->assert($request, 'tickets.view');
        $user = $request->user();
        $query = Ticket::query()->whereNotNull('sla_due_at')->whereNotIn('status', [Ticket::STATUS_CLOSED, Ticket::STATUS_RESOLVED]);
        if (! $this->access->isSuperAdmin($user) && ! $user->isTicketSupervisor()) {
            $query->where('assigned_to', $user->id);
        } elseif (filled($user->department_code) && ! $this->access->isSuperAdmin($user)) {
            $query->where('assigned_department', $user->department_code);
        }

        return $this->ticketList($request, $query, 'SLA Monitoring');
    }

    public function notifications(Request $request): View
    {
        $this->assert($request, 'notifications.view');
        $filter = (string) $request->query('filter', 'all');
        $query = AppNotification::query()->where('user_id', $request->user()->id);
        if ($filter === 'unread') {
            $query->whereNull('read_at');
        } elseif ($filter === 'read') {
            $query->whereNotNull('read_at');
        } elseif (in_array($filter, ['assignment', 'escalation', 'system', 'ticket'], true)) {
            $query->where('category', $filter === 'ticket' ? 'assignment' : $filter);
        }

        $notifications = $query->latest()->paginate(25)->withQueryString();

        return view('pages.staff.access.notifications', compact('notifications', 'filter'));
    }

    private function departmentTicketQuery(User $user)
    {
        $query = Ticket::query();
        if (! $user->canOpenUnassignedTickets()) {
            return $query->where('assigned_to', $user->id);
        }

        if (! $this->access->isSuperAdmin($user) && filled($user->department_code)) {
            return $query->where('assigned_department', $user->department_code);
        }

        return $query;
    }

    private function departmentScopeLabel(User $user): string
    {
        if (! $user->canOpenUnassignedTickets()) {
            return 'Tickets assigned to you';
        }

        if (! $this->access->isSuperAdmin($user) && filled($user->department_code)) {
            return $user->department_code.' department';
        }

        return 'All departments';
    }

    private function ticketList(Request $request, $query, string $title): View
    {
        $list = ListQuery::from($request, ['status', 'priority'], ['id' => 'id', 'sla_due_at' => 'sla_due_at'], 'id', 'desc');
        if ($list['search']) {
            $term = $list['search'];
            $query->where(fn ($q) => $q->where('ticket_no', 'like', "%{$term}%"));
        }
        foreach (['status', 'priority'] as $key) {
            if (! empty($list['filters'][$key])) {
                $query->where($key, $list['filters'][$key]);
            }
        }
        $tickets = $query->with(['category', 'customer', 'assignee'])->latest()->paginate($list['per_page'])->withQueryString();

        return view('pages.staff.access.ticket-list', [
            'title' => $title,
            'tickets' => $tickets,
            'list' => $list,
            'filters' => array_merge($list['filters'], ['search' => $list['search']]),
            'activeFilterCount' => $list['active_filter_count'],
        ]);
    }

    private function assert(Request $request, string $permission): void
    {
        if (! $this->access->allows($request->user(), $permission)) {
            abort(403);
        }
    }
}
