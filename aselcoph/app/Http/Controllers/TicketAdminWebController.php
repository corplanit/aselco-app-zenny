<?php

namespace App\Http\Controllers;

use App\Models\AccountLink;
use App\Models\AppNotification;
use App\Models\Ticket;
use App\Models\TicketAiResponseDraft;
use App\Models\TicketCategory;
use App\Models\TicketEscalation;
use App\Models\User;
use App\Services\Ai\TicketAiAnalyzer;
use App\Services\Ai\TicketAiResponseAssistant;
use App\Services\TicketService;
use App\Support\TicketUi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

class TicketAdminWebController extends Controller
{
    public function __construct(
        private TicketService $tickets,
        private TicketAiAnalyzer $ticketAi,
        private TicketAiResponseAssistant $ticketAiResponses,
    ) {
    }

    public function queue(Request $request): View
    {
        $user = $request->user();
        $this->assertCanManage($user);

        $sortable = [
            'id' => 'ID',
            'ticket_no' => 'Ticket no.',
            'created_at' => 'Created',
            'sla_due_at' => 'SLA due',
            'priority' => 'Priority',
            'status' => 'Status',
        ];

        $list = \App\Support\ListQuery::from(
            $request,
            filterKeys: ['status', 'category_id', 'assigned_department', 'overdue', 'open', 'priority'],
            sortable: $sortable,
            defaultSort: 'id',
            defaultDir: 'desc',
            defaultPerPage: 25,
        );

        $filters = array_merge($list['filters'], [
            'search' => $list['search'],
            'sort' => $list['sort'],
            'dir' => $list['dir'],
            'per_page' => $list['per_page'],
        ]);

        if (! $user->canSeeAllTicketDepartments() && ! $user->isTicketCsr() && filled($user->department_code)) {
            $filters['assigned_department'] = $user->department_code;
        }

        if (empty($filters['status']) && ! $request->exists('open') && ! $request->exists('status')) {
            $filters['open'] = true;
        }

        $tickets = $this->tickets->listAdmin($user, $filters);
        $categories = TicketCategory::query()->orderBy('id')->get();
        $departments = $this->departmentOptions();
        $wideView = $user->canSeeAllTicketDepartments() || $user->isTicketCsr();
        $activeFilterCount = $list['active_filter_count'];
        if (! empty($filters['open']) && empty($list['filters']['status'])) {
            // default open filter shouldn't inflate badge when user didn't choose filters
            if (! $request->hasAny(['search', 'status', 'category_id', 'assigned_department', 'overdue', 'priority'])) {
                $activeFilterCount = 0;
            }
        }

        return view('pages.staff.tickets.queue', compact(
            'tickets',
            'categories',
            'departments',
            'filters',
            'wideView',
            'list',
            'sortable',
            'activeFilterCount',
        ));
    }

    public function intake(Request $request): View
    {
        $this->assertCanIntake($request->user());

        $categories = TicketCategory::query()->orderBy('id')->get();
        $presetCustomer = null;
        $customerId = (int) $request->query('customer');
        if ($customerId > 0) {
            $presetCustomer = User::query()->find($customerId);
        }

        return view('pages.staff.tickets.intake', compact('categories', 'presetCustomer'));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->assertCanIntake($request->user());

        $validated = $request->validate([
            'customer_id' => ['required', 'integer', 'exists:users,id'],
            'category_id' => ['required', 'integer', 'exists:ticket_categories,id'],
            'subcategory' => ['nullable', 'string', 'max:160'],
            'channel' => ['required', 'in:call,text,social/sms,app'],
            'description' => ['required', 'string', 'max:5000'],
            'priority' => ['nullable', 'in:low,normal,high,urgent'],
            'attachment' => [
                'nullable',
                'file',
                'max:'.config('tickets.attachment.max_kb', 10240),
                'mimes:'.implode(',', config('tickets.attachment.allowed_mimes', [])),
            ],
        ]);

        try {
            $ticket = $this->tickets->createAdminTicket($request->user(), $validated);
            $ticket = $this->tickets->endorse($request->user(), $ticket->id, [
                'remarks' => 'Auto-endorsed from CSR intake (Phase 5 routing).',
            ]);

            if ($request->hasFile('attachment')) {
                $this->tickets->addAttachment($request->user(), $ticket->id, $request->file('attachment'));
            }
        } catch (HttpException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('tickets.show', $ticket->id)
            ->with('success', 'Ticket '.$ticket->ticket_no.' created and routed to '.$ticket->assigned_department.'.');
    }

    public function customerSearch(Request $request): JsonResponse
    {
        $this->assertCanManage($request->user());

        $q = trim((string) $request->query('q', ''));
        if (strlen($q) < 2) {
            return response()->json(['data' => []]);
        }

        $fromLinks = AccountLink::query()
            ->where(function ($query) use ($q) {
                $query->where('account_number', 'like', "%{$q}%")
                    ->orWhere('owner_name', 'like', "%{$q}%");
            })
            ->with('user:id,name,email,contact_no,role,email_verified_at,profile_photo_path')
            ->limit(10)
            ->get()
            ->filter(fn ($link) => $link->user && $link->user->isActiveCustomer())
            ->map(fn ($link) => $this->intakeCustomerPayload($link->user, $link->account_number));

        $fromUsers = User::query()
            ->where(function ($query) use ($q) {
                $query->where('name', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%")
                    ->orWhere('contact_no', 'like', "%{$q}%");
            })
            ->limit(10)
            ->get(['id', 'name', 'email', 'contact_no', 'role', 'email_verified_at', 'profile_photo_path'])
            ->filter(fn (User $user) => $user->isActiveCustomer())
            ->map(fn (User $user) => $this->intakeCustomerPayload($user));

        $data = $fromLinks->concat($fromUsers)->unique('id')->values()->take(10);

        return response()->json(['data' => $data]);
    }

    public function createCustomer(Request $request): JsonResponse
    {
        $this->assertCanIntake($request->user());

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'email' => ['nullable', 'email', 'max:160', 'unique:users,email'],
            'contact_no' => ['nullable', 'string', 'max:40'],
            'account_number' => ['nullable', 'string', 'max:50'],
        ]);

        if (empty($validated['email']) && empty($validated['contact_no'])) {
            return response()->json(['message' => 'Provide an email or contact number for the walk-in customer.'], 422);
        }

        $email = $validated['email'] ?? ('walkin+'.Str::lower(Str::random(10)).'@tickets.aselco.local');

        $user = User::query()->create([
            'name' => $validated['name'],
            'email' => $email,
            'contact_no' => $validated['contact_no'] ?? null,
            'role' => 'User',
            'password' => Hash::make(Str::password(12)),
            'email_verified_at' => now(),
        ]);

        if (! empty($validated['account_number'])) {
            AccountLink::query()->create([
                'user_id' => $user->id,
                'account_number' => $validated['account_number'],
                'owner_name' => $validated['name'],
            ]);
        }

        return response()->json(
            $this->intakeCustomerPayload($user, $validated['account_number'] ?? null),
            201
        );
    }

    public function show(Request $request, int $id): View
    {
        $this->assertCanManage($request->user());

        try {
            $ticket = $this->tickets->showAdmin($request->user(), $id);
        } catch (HttpException $e) {
            abort($e->getStatusCode(), $e->getMessage());
        }

        $user = $request->user();
        $canLogAction = $this->canLogAction($user, $ticket);
        $canStartProgress = $canLogAction && $ticket->status === Ticket::STATUS_ASSIGNED;
        $canLogActionNow = $canLogAction && ! $canStartProgress;
        $canLogFeedback = $user->canVerifyTicketFeedback();
        $canEscalate = $user->canSeeAllTicketDepartments() || $user->isTicketCsr() || $this->canLogAction($user, $ticket);
        $canClose = $this->tickets->hasConfirmingResolutionFeedback($ticket)
            && ! in_array($ticket->status, [Ticket::STATUS_CLOSED], true)
            && $canLogFeedback;
        $departments = $this->departmentOptions();
        $assignees = $this->assignableStaff();
        $assigneeRoles = $assignees->pluck('role')->filter()->unique()->sort()->values();
        $canAssign = $user->canAssignTickets()
            && ! in_array($ticket->status, [Ticket::STATUS_CLOSED, Ticket::STATUS_RESOLVED], true);

        $timeline = $this->buildTimeline($ticket);
        $aiAnalysis = $ticket->latestAiAnalysis()->with(['recommendedCategory', 'suggestedAssignee'])->first();
        $aiDrafts = TicketAiResponseDraft::query()
            ->where('ticket_id', $ticket->id)
            ->orderByDesc('id')
            ->limit(5)
            ->get();

        return view('pages.staff.tickets.show', compact(
            'ticket',
            'canLogAction',
            'canStartProgress',
            'canLogActionNow',
            'canLogFeedback',
            'canEscalate',
            'canClose',
            'departments',
            'assignees',
            'assigneeRoles',
            'canAssign',
            'timeline',
            'aiAnalysis',
            'aiDrafts',
        ));
    }

    public function aiDashboard(Request $request): View
    {
        $this->assertCanManage($request->user());

        return view('pages.staff.tickets.ai-dashboard', [
            'stats' => $this->ticketAiResponses->dashboardStats(),
        ]);
    }

    public function aiAnalyze(Request $request, int $id): RedirectResponse
    {
        $this->assertCanManage($request->user());

        try {
            $ticket = $this->tickets->showAdmin($request->user(), $id);
            $this->ticketAi->analyze($ticket, $request->user());
        } catch (HttpException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'AI analysis refreshed (advisory only — routing unchanged).');
    }

    public function aiApplyPriority(Request $request, int $id): RedirectResponse
    {
        $this->assertCanManage($request->user());
        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $ticket = $this->tickets->showAdmin($request->user(), $id);
            $analysis = $ticket->latestAiAnalysis;
            if ($analysis === null) {
                return back()->with('error', 'No AI analysis available.');
            }
            $this->ticketAi->applyPriorityRecommendation($request->user(), $ticket, $analysis, $validated['reason'] ?? null);
        } catch (HttpException|\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Priority updated from AI recommendation by authorized staff.');
    }

    public function aiSuggestResponse(Request $request, int $id): RedirectResponse
    {
        $this->assertCanManage($request->user());
        $validated = $request->validate([
            'guidance' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $ticket = $this->tickets->showAdmin($request->user(), $id);
            $this->ticketAiResponses->suggest($ticket, $request->user(), $validated['guidance'] ?? null);
        } catch (HttpException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'AI draft created for CSR review. Nothing was sent to the customer.');
    }

    public function aiReviewDraft(Request $request, int $id, int $draftId): RedirectResponse
    {
        $this->assertCanManage($request->user());
        $validated = $request->validate([
            'decision' => ['required', 'in:approve,reject'],
            'edited_body' => ['nullable', 'string', 'max:5000'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $this->tickets->showAdmin($request->user(), $id);
            $draft = TicketAiResponseDraft::query()->where('ticket_id', $id)->whereKey($draftId)->firstOrFail();
            $result = $this->ticketAiResponses->review(
                $request->user(),
                $draft,
                $validated['decision'],
                $validated['edited_body'] ?? null,
                $validated['notes'] ?? null,
            );
        } catch (HttpException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', $result['message']);
    }

    public function startProgress(Request $request, int $id): RedirectResponse
    {
        $this->assertCanManage($request->user());

        try {
            $this->tickets->startProgress($request->user(), $id);
        } catch (HttpException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Work started. Ticket is now In Progress.');
    }

    public function action(Request $request, int $id): RedirectResponse
    {
        $this->assertCanManage($request->user());

        $validated = $request->validate([
            'action_taken' => ['required', 'string', 'max:5000'],
            'minutes_taken' => ['required', 'integer', 'min:0'],
            'requires_payment' => ['nullable', 'boolean'],
            'requires_tsd_intervention' => ['nullable', 'boolean'],
            'tsd_reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $validated['requires_payment'] = $request->boolean('requires_payment');
        $validated['requires_tsd_intervention'] = $request->boolean('requires_tsd_intervention');

        try {
            $this->tickets->addAction($request->user(), $id, $validated);
        } catch (HttpException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Action logged. Ticket moved to awaiting customer verification.');
    }

    public function feedback(Request $request, int $id): RedirectResponse
    {
        $this->assertCanIntake($request->user());

        $validated = $request->validate([
            'method' => ['required', 'in:call,message'],
            'customer_confirmed' => ['required', 'boolean'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'needs_further_action' => ['required', 'boolean'],
        ]);

        $validated['customer_confirmed'] = $request->boolean('customer_confirmed');
        $validated['needs_further_action'] = $request->boolean('needs_further_action');

        try {
            $ticket = $this->tickets->addStaffFeedback($request->user(), $id, $validated);
        } catch (HttpException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        $message = $ticket->status === Ticket::STATUS_CLOSED
            ? 'Verification recorded. Ticket closed.'
            : 'Further action requested. Ticket reopened and re-routed.';

        return back()->with('success', $message);
    }

    public function escalate(Request $request, int $id): RedirectResponse
    {
        $this->assertCanManage($request->user());

        $validated = $request->validate([
            'escalated_to' => ['required', 'string', 'max:40'],
            'reason' => ['required', 'string', 'max:5000'],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        try {
            $this->tickets->escalate($request->user(), $id, $validated);
        } catch (HttpException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Ticket escalated to '.$validated['escalated_to'].'.');
    }

    public function close(Request $request, int $id): RedirectResponse
    {
        $this->assertCanIntake($request->user());

        $validated = $request->validate([
            'remarks' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $this->tickets->close($request->user(), $id, $validated['remarks'] ?? null);
        } catch (HttpException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Ticket closed.');
    }

    public function attach(Request $request, int $id): RedirectResponse
    {
        $this->assertCanManage($request->user());

        $request->validate([
            'attachment' => [
                'required',
                'file',
                'max:'.config('tickets.attachment.max_kb', 10240),
                'mimes:'.implode(',', config('tickets.attachment.allowed_mimes', [])),
            ],
        ]);

        try {
            $this->tickets->addAttachment($request->user(), $id, $request->file('attachment'));
        } catch (HttpException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Attachment uploaded.');
    }

    public function downloadAttachment(Request $request, int $attachment): StreamedResponse
    {
        $this->assertCanManage($request->user());

        try {
            $download = $this->tickets->downloadAttachment($request->user(), $attachment);
        } catch (HttpException $e) {
            abort($e->getStatusCode(), $e->getMessage());
        }

        return Storage::disk($download['disk'])->download($download['path'], $download['name'], [
            'Content-Type' => $download['mime'],
        ]);
    }

    public function escalations(Request $request): View
    {
        $user = $request->user();
        $this->assertCanManage($user);

        $list = \App\Support\ListQuery::from(
            $request,
            filterKeys: ['escalated_to'],
            sortable: ['id' => 'id', 'escalated_at' => 'escalated_at'],
            defaultSort: 'id',
            defaultDir: 'desc',
            defaultPerPage: 25,
        );

        $query = TicketEscalation::query()
            ->with(['ticket.customer:id,name,email', 'ticket.category', 'ticket.assignee:id,name,department_code'])
            ->whereNull('resolved_at');

        if (! $user->canOpenUnassignedTickets()) {
            $query->whereHas('ticket', fn ($ticket) => $ticket->where('assigned_to', $user->id));
        } elseif (! $user->canSeeAllTicketDepartments() && filled($user->department_code)) {
            $query->whereHas('ticket', function ($ticket) use ($user) {
                $ticket->where('assigned_department', $user->department_code)
                    ->orWhere('assigned_to', $user->id);
            });
        }

        $stats = [
            'open' => (clone $query)->count(),
            'unassigned' => (clone $query)->whereHas('ticket', fn ($ticket) => $ticket->whereNull('assigned_to'))->count(),
            'overdue' => (clone $query)->whereHas('ticket', function ($ticket) {
                $ticket->whereNotNull('sla_due_at')
                    ->where('sla_due_at', '<', now())
                    ->whereNotIn('status', [Ticket::STATUS_CLOSED, Ticket::STATUS_RESOLVED]);
            })->count(),
        ];

        if (! empty($list['search'])) {
            $term = $list['search'];
            $query->where(function ($inner) use ($term) {
                $inner->where('escalated_to', 'like', "%{$term}%")
                    ->orWhere('reason', 'like', "%{$term}%")
                    ->orWhereHas('ticket', function ($ticket) use ($term) {
                        $ticket->where('ticket_no', 'like', "%{$term}%")
                            ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$term}%"));
                    });
            });
        }

        if (! empty($list['filters']['escalated_to'])) {
            $query->where('escalated_to', $list['filters']['escalated_to']);
        }

        $sortCol = $list['sort'] === 'escalated_at' ? 'escalated_at' : 'id';
        $escalations = $query->orderBy($sortCol, $list['dir'])->paginate($list['per_page'])->withQueryString();
        $departments = $this->departmentOptions();
        $assignees = $this->assignableStaff();
        $assigneeRoles = $assignees->pluck('role')->filter()->unique()->sort()->values();
        $filters = array_merge($list['filters'], [
            'search' => $list['search'],
            'sort' => $list['sort'],
            'dir' => $list['dir'],
        ]);
        $activeFilterCount = $list['active_filter_count'];
        $canAssign = $user->canAssignTickets();

        return view('pages.staff.tickets.escalations', compact(
            'escalations',
            'departments',
            'assignees',
            'assigneeRoles',
            'stats',
            'list',
            'filters',
            'activeFilterCount',
            'canAssign',
        ));
    }

    public function reassign(Request $request, int $id): RedirectResponse
    {
        $this->assertCanManage($request->user());

        if (! $request->user()->canAssignTickets()) {
            abort(403, 'Only supervisors, admins, or CSR can reassign tickets.');
        }

        $validated = $request->validate([
            'assigned_department' => ['required', 'string', 'max:40'],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $this->tickets->reassign($request->user(), $id, $validated);
        } catch (HttpException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Ticket reassigned.');
    }

    public function reports(Request $request): View|StreamedResponse|RedirectResponse
    {
        $user = $request->user();
        $this->assertCanManage($user);

        $filters = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $analytics = $this->tickets->staffAnalytics($user, $filters);

        if ($request->query('export') === 'csv') {
            return $this->exportReportsCsv($analytics);
        }

        return redirect()->route('workspace.department', array_filter([
            'from' => $filters['from'] ?? null,
            'to' => $filters['to'] ?? null,
        ]));
    }

    public function notifications(Request $request): JsonResponse
    {
        $this->assertCanManage($request->user());

        $items = AppNotification::query()
            ->where('user_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        $unread = AppNotification::query()
            ->where('user_id', $request->user()->id)
            ->whereNull('read_at')
            ->count();

        return response()->json([
            'unread_count' => $unread,
            'data' => $items->map(fn (AppNotification $n) => [
                'id' => $n->id,
                'title' => $n->title,
                'body' => $n->body,
                'category' => $n->category,
                'data' => $n->data,
                'unread' => $n->read_at === null,
                'created_at' => $n->created_at?->diffForHumans(),
                'ticket_url' => $this->notificationTicketUrl($n),
            ]),
        ]);
    }

    public function markNotificationRead(Request $request, int $id): JsonResponse
    {
        $this->assertCanManage($request->user());

        $notification = AppNotification::query()
            ->where('user_id', $request->user()->id)
            ->whereKey($id)
            ->firstOrFail();

        if ($notification->read_at === null) {
            $notification->read_at = now();
            $notification->save();
        }

        return response()->json(['ok' => true]);
    }

    public function markAllNotificationsRead(Request $request): JsonResponse
    {
        $this->assertCanManage($request->user());

        AppNotification::query()
            ->where('user_id', $request->user()->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(['ok' => true]);
    }

    private function exportReportsCsv(array $analytics): StreamedResponse
    {
        $filename = 'ticket-report-'.$analytics['from'].'-to-'.$analytics['to'].'.csv';

        return response()->streamDownload(function () use ($analytics) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Tickets by category']);
            fputcsv($out, ['Category', 'Department', 'Tickets', 'Closed', 'Avg resolution (min)', 'SLA target (min)', 'Escalations', 'Escalation rate %']);
            foreach ($analytics['by_category'] as $row) {
                fputcsv($out, [
                    $row['name'],
                    $row['department_code'],
                    $row['ticket_count'],
                    $row['closed_count'],
                    $row['avg_resolution_minutes'],
                    $row['sla_minutes'],
                    $row['escalation_count'],
                    $row['escalation_rate'],
                ]);
            }
            fputcsv($out, []);
            fputcsv($out, ['Department performance']);
            fputcsv($out, ['Department', 'Tickets', 'Closed', 'Overdue', 'Action minutes']);
            foreach ($analytics['by_department'] as $row) {
                fputcsv($out, [$row['department'], $row['ticket_count'], $row['closed_count'], $row['overdue_count'], $row['action_minutes']]);
            }
            fputcsv($out, []);
            fputcsv($out, ['CSR intake']);
            fputcsv($out, ['Staff', 'Intake tickets', 'Verifications in range']);
            foreach ($analytics['by_csr'] as $row) {
                fputcsv($out, [$row['name'], $row['intake_count'], $row['verified_count']]);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    private function notificationTicketUrl(AppNotification $notification): ?string
    {
        $data = $notification->data ?? [];
        $ticketId = $data['ticket_id'] ?? null;
        if ($ticketId) {
            return route('tickets.show', $ticketId);
        }

        $deep = $data['deep_link'] ?? null;
        if (is_string($deep) && str_starts_with($deep, '/tickets/')) {
            $id = trim(str_replace('/tickets/', '', $deep), '/');
            if (ctype_digit($id)) {
                return route('tickets.show', $id);
            }
        }

        return null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buildTimeline(Ticket $ticket): array
    {
        $items = [];

        foreach ($ticket->statusHistory as $history) {
            $items[] = [
                'at' => $history->created_at,
                'kind' => 'status',
                'status' => $history->to_status,
                'title' => TicketUi::statusLabel($history->from_status).' → '.TicketUi::statusLabel($history->to_status),
                'body' => $history->remarks,
                'actor' => $history->actor?->name,
            ];
        }

        $actionStatus = in_array($ticket->status, [
            Ticket::STATUS_IN_PROGRESS,
            Ticket::STATUS_ASSIGNED,
            Ticket::STATUS_ESCALATED,
            Ticket::STATUS_REOPENED,
        ], true) ? $ticket->status : Ticket::STATUS_ASSIGNED;

        foreach ($ticket->actions as $action) {
            $items[] = [
                'at' => $action->created_at,
                'kind' => 'action',
                'status' => $actionStatus,
                'title' => 'Crew / department action ('.$action->minutes_taken.' min)',
                'body' => $action->action_taken,
                'actor' => $action->actor?->name ?? $action->actor_role,
            ];
        }

        foreach ($ticket->feedback as $feedback) {
            $items[] = [
                'at' => $feedback->created_at,
                'kind' => 'feedback',
                'status' => Ticket::STATUS_AWAITING_FEEDBACK,
                'title' => 'Verification ('.$feedback->method.')',
                'body' => trim(
                    ($feedback->customer_confirmed ? 'Customer confirmed resolved. ' : 'Customer did not confirm. ')
                    .($feedback->needs_further_action ? 'Further action needed. ' : '')
                    .(string) $feedback->notes
                ),
                'actor' => $feedback->verifier?->name ?? 'Customer',
            ];
        }

        foreach ($ticket->escalations as $escalation) {
            $items[] = [
                'at' => $escalation->escalated_at,
                'kind' => 'escalation',
                'status' => Ticket::STATUS_ESCALATED,
                'title' => 'Escalated '.$escalation->escalated_from.' → '.$escalation->escalated_to,
                'body' => $escalation->reason,
                'actor' => null,
            ];
        }

        usort($items, function ($a, $b) {
            return ($a['at']?->timestamp ?? 0) <=> ($b['at']?->timestamp ?? 0);
        });

        return $items;
    }

    private function canLogAction(User $user, Ticket $ticket): bool
    {
        if (in_array($ticket->status, [Ticket::STATUS_CLOSED, Ticket::STATUS_RESOLVED, Ticket::STATUS_AWAITING_FEEDBACK], true)) {
            return false;
        }

        if ($user->canSeeAllTicketDepartments()) {
            return true;
        }

        if ((int) $ticket->assigned_to === (int) $user->id) {
            return true;
        }

        return filled($user->department_code)
            && $ticket->assigned_department === $user->department_code;
    }

    /**
     * @return list<string>
     */
    private function departmentOptions(): array
    {
        $fromCategories = TicketCategory::query()->pluck('department_code')->all();

        return collect(array_merge($fromCategories, [
            config('tickets.assignment.distribution_night_department', 'GUARD'),
            config('tickets.assignment.tsd_department', 'TSD'),
            config('tickets.escalation.second_tier_department', 'AREA-ADMIN'),
        ]))->filter()->unique()->values()->all();
    }

    /**
     * Support accounts assignable by department or role.
     *
     * @return \Illuminate\Support\Collection<int, User>
     */
    private function assignableStaff()
    {
        $staffRoles = User::assignableStaffRoles();

        return User::query()
            ->where(function ($query) use ($staffRoles) {
                $query->whereIn('role', $staffRoles)
                    ->orWhere(function ($inner) {
                        $inner->whereNotNull('department_code')
                            ->where('department_code', '!=', '');
                    });
            })
            ->where(function ($query) {
                $query->whereNull('role')
                    ->orWhereNotIn('role', array_merge(
                        config('tickets.admin_roles', []),
                        ['User'],
                    ));
            })
            ->orderByRaw("CASE WHEN department_code IS NULL OR department_code = '' THEN 1 ELSE 0 END")
            ->orderBy('department_code')
            ->orderBy('name')
            ->get(['id', 'name', 'department_code', 'role']);
    }

    /**
     * @return \Illuminate\Support\Collection<int, User>
     */
    private function assigneesFor(?string $department)
    {
        if (! $department) {
            return $this->assignableStaff();
        }

        return $this->assignableStaff()
            ->filter(fn (User $person) => $person->department_code === $department
                || in_array((string) $person->role, User::assignableStaffRoles(), true))
            ->values();
    }

    private function assertCanManage(?User $user): void
    {
        if ($user === null || ! $user->canManageTickets()) {
            abort(403, 'You are not allowed to manage tickets.');
        }
    }

    private function assertCanIntake(?User $user): void
    {
        if ($user === null || ! $user->canIntakeTickets()) {
            abort(403, 'Only CSR, supervisors, and admins can log intake tickets.');
        }
    }

    /**
     * @return array{id: int, name: string, email: ?string, contact_no: ?string, account_number: ?string, photo_url: string}
     */
    private function intakeCustomerPayload(User $user, ?string $accountNumber = null): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'contact_no' => $user->contact_no,
            'account_number' => $accountNumber,
            'photo_url' => $user->profile_photo_url ?: asset('user.png'),
        ];
    }
}
