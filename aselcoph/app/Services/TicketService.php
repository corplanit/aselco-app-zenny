<?php

namespace App\Services;

use App\Events\TicketLifecycleEvent;
use App\Models\Department;
use App\Models\Ticket;
use App\Models\TicketAction;
use App\Models\TicketAiAnalysis;
use App\Models\TicketAssignmentHistory;
use App\Models\TicketAttachment;
use App\Models\TicketCategory;
use App\Models\TicketEscalation;
use App\Models\TicketFeedback;
use App\Models\TicketStatusHistory;
use App\Models\AccessSetting;
use App\Models\User;
use App\Support\TicketUi;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

class TicketService
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function createAdminTicket(User $actor, array $payload): Ticket
    {
        return $this->createTicket($actor, (int) $payload['customer_id'], [
            ...$payload,
            'created_by' => (string) $actor->id,
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function createCustomerTicket(User $customer, array $payload): Ticket
    {
        return $this->createTicket($customer, $customer->id, [
            ...$payload,
            'created_by' => 'customer',
        ]);
    }

    public function listAdmin(User $actor, array $filters = []): LengthAwarePaginator
    {
        $sortable = [
            'id' => 'tickets.id',
            'ticket_no' => 'tickets.ticket_no',
            'created_at' => 'tickets.created_at',
            'sla_due_at' => 'tickets.sla_due_at',
            'priority' => 'tickets.priority',
            'status' => 'tickets.status',
        ];
        $sort = (string) ($filters['sort'] ?? 'id');
        $dir = strtolower((string) ($filters['dir'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';
        $column = $sortable[$sort] ?? 'tickets.id';

        return $this->staffVisibleQuery($actor)
            ->with(['category', 'customer:id,name,email', 'assignee:id,name,department_code'])
            ->when(! empty($filters['status']), fn (Builder $q) => $q->where('status', $filters['status']))
            ->when(! empty($filters['category_id']), fn (Builder $q) => $q->where('category_id', $filters['category_id']))
            ->when(! empty($filters['assigned_department']), fn (Builder $q) => $q->where('assigned_department', $filters['assigned_department']))
            ->when(! empty($filters['priority']), fn (Builder $q) => $q->where('priority', $filters['priority']))
            ->when(! empty($filters['search']), function (Builder $q) use ($filters) {
                $term = trim((string) $filters['search']);
                $q->where(function (Builder $inner) use ($term) {
                    $inner->where('ticket_no', 'like', "%{$term}%")
                        ->orWhere('description', 'like', "%{$term}%")
                        ->orWhere('subcategory', 'like', "%{$term}%")
                        ->orWhereHas('customer', function (Builder $customer) use ($term) {
                            $customer->where('name', 'like', "%{$term}%")
                                ->orWhere('email', 'like', "%{$term}%");
                        });
                });
            })
            ->when(! empty($filters['assigned_to']), fn (Builder $q) => $q->where('assigned_to', $filters['assigned_to']))
            ->when(! empty($filters['open']), function (Builder $q) {
                $q->whereNotIn('status', [Ticket::STATUS_CLOSED, Ticket::STATUS_RESOLVED]);
            })
            ->when(! empty($filters['overdue']), function (Builder $q) {
                $q->whereNotNull('sla_due_at')
                    ->where('sla_due_at', '<=', now())
                    ->whereNotIn('status', [Ticket::STATUS_CLOSED, Ticket::STATUS_RESOLVED]);
            })
            ->orderBy($column, $dir)
            ->paginate((int) ($filters['per_page'] ?? 15));
    }

    public function listCustomer(User $customer, array $filters = []): LengthAwarePaginator
    {
        return Ticket::query()
            ->where('customer_id', $customer->id)
            ->with(['category', 'assignee:id,name,department_code'])
            ->when(! empty($filters['status']), fn (Builder $q) => $q->where('status', $filters['status']))
            ->orderByDesc('id')
            ->paginate((int) ($filters['per_page'] ?? 15));
    }

    public function showAdmin(User $actor, int $ticketId): Ticket
    {
        return $this->loadTicket($this->findStaffTicket($actor, $ticketId));
    }

    public function showCustomer(User $customer, int $ticketId): Ticket
    {
        $ticket = Ticket::query()->where('customer_id', $customer->id)->whereKey($ticketId)->first();
        if (! $ticket) {
            throw new HttpException(404, 'Ticket not found.');
        }

        return $this->loadTicket($ticket);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function endorse(User $actor, int $ticketId, array $payload = []): Ticket
    {
        $ticket = $this->findStaffTicket($actor, $ticketId);
        $previousDepartment = $ticket->assigned_department;
        $previousAssignee = $ticket->assigned_to;
        $category = $ticket->category;
        $assignedDepartment = (string) ($payload['assigned_department'] ?? $this->resolveDepartmentForCategory($category));
        $method = array_key_exists('assigned_to', $payload)
            ? TicketAssignmentHistory::METHOD_MANUAL
            : (string) config('tickets.assignment.strategy', 'round_robin');
        $assignedTo = array_key_exists('assigned_to', $payload)
            ? $payload['assigned_to']
            : $this->selectAssigneeId($assignedDepartment, $category->default_assignee_role, $ticket);

        $ticket = $this->advanceAlongPath($ticket, Ticket::STATUS_ASSIGNED, $actor, (string) ($payload['remarks'] ?? 'Ticket endorsed and assigned.'), [
            'assigned_department' => $assignedDepartment,
            'assigned_to' => $assignedTo,
            'sla_due_at' => now()->addMinutes((int) $category->sla_minutes),
        ]);

        TicketLifecycleEvent::dispatch('ticket.endorsed', $ticket, $actor, [
            'department' => $assignedDepartment,
        ]);
        TicketLifecycleEvent::dispatch('ticket.assigned', $ticket, $actor, [
            'department' => $assignedDepartment,
            'assigned_to' => $assignedTo,
        ]);
        $this->recordAssignmentHistory($ticket, $actor, $previousDepartment, $previousAssignee, $method, $payload['remarks'] ?? 'Ticket endorsed and assigned.');

        return $ticket;
    }

    public function startProgress(User $actor, int $ticketId): Ticket
    {
        $ticket = $this->findStaffTicket($actor, $ticketId);

        if ($ticket->status === Ticket::STATUS_IN_PROGRESS) {
            return $ticket;
        }

        if (! in_array($ticket->status, [Ticket::STATUS_NEW, Ticket::STATUS_ENDORSED, Ticket::STATUS_ASSIGNED], true)) {
            throw new HttpException(422, 'Start work is only available while the ticket is assigned.');
        }

        $ticket = $this->advanceAlongPath($ticket, Ticket::STATUS_IN_PROGRESS, $actor, 'Work started on this ticket.');
        TicketLifecycleEvent::dispatch('ticket.in_progress', $ticket, $actor);

        return $ticket;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function addAction(User $actor, int $ticketId, array $payload): Ticket
    {
        $ticket = $this->findStaffTicket($actor, $ticketId);

        if ($ticket->category->requires_payment_check && ! array_key_exists('requires_payment', $payload)) {
            throw new HttpException(422, 'Billing tickets require a requires_payment decision.');
        }

        TicketAction::query()->create([
            'ticket_id' => $ticket->id,
            'actor_id' => $actor->id,
            'actor_role' => $actor->role ?: $actor->department_code,
            'action_taken' => $payload['action_taken'],
            'minutes_taken' => (int) $payload['minutes_taken'],
            'requires_payment' => $payload['requires_payment'] ?? null,
            'requires_tsd_intervention' => $payload['requires_tsd_intervention'] ?? null,
        ]);

        TicketLifecycleEvent::dispatch('ticket.action_logged', $ticket, $actor, [
            'requires_payment' => $payload['requires_payment'] ?? null,
            'requires_tsd_intervention' => $payload['requires_tsd_intervention'] ?? null,
        ]);

        if ($ticket->category->requires_tsd_check && ! empty($payload['requires_tsd_intervention'])) {
            return $this->escalateToTsd($ticket, $actor, (string) ($payload['tsd_reason'] ?? 'Distribution issue requires TSD intervention.'));
        }

        $ticket = $this->advanceAlongPath($ticket, Ticket::STATUS_AWAITING_FEEDBACK, $actor, 'Action logged.');
        TicketLifecycleEvent::dispatch('ticket.feedback_requested', $ticket, $actor);

        return $ticket;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function addStaffFeedback(User $actor, int $ticketId, array $payload): Ticket
    {
        $ticket = $this->findStaffTicket($actor, $ticketId);

        TicketFeedback::query()->create([
            'ticket_id' => $ticket->id,
            'verified_by' => $actor->id,
            'method' => $payload['method'],
            'customer_confirmed' => (bool) $payload['customer_confirmed'],
            'notes' => $payload['notes'] ?? null,
            'needs_further_action' => (bool) $payload['needs_further_action'],
        ]);

        $to = ! empty($payload['needs_further_action']) ? Ticket::STATUS_REOPENED : Ticket::STATUS_CLOSED;
        $ticket = $this->changeStatus($ticket, $to, $actor, (string) ($payload['notes'] ?? 'Feedback logged.'));

        if ($to === Ticket::STATUS_REOPENED) {
            TicketLifecycleEvent::dispatch('ticket.reopened', $ticket, $actor);

            return $this->endorse($actor, $ticket->id, [
                'remarks' => 'Further action requested by CSR feedback.',
                'assigned_department' => $ticket->assigned_department,
            ]);
        }

        TicketLifecycleEvent::dispatch('ticket.resolved', $ticket, $actor);
        TicketLifecycleEvent::dispatch('ticket.closed', $ticket, $actor);

        return $ticket;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function addCustomerFeedback(User $customer, int $ticketId, array $payload): Ticket
    {
        $ticket = $this->showCustomer($customer, $ticketId);

        TicketFeedback::query()->create([
            'ticket_id' => $ticket->id,
            'verified_by' => null,
            'method' => $payload['method'],
            'customer_confirmed' => (bool) $payload['customer_confirmed'],
            'notes' => $payload['notes'] ?? null,
            'needs_further_action' => ! (bool) $payload['customer_confirmed'],
        ]);

        $to = ! empty($payload['customer_confirmed']) ? Ticket::STATUS_CLOSED : Ticket::STATUS_REOPENED;
        $ticket = $this->changeStatus($ticket, $to, $customer, (string) ($payload['notes'] ?? 'Customer feedback logged.'));

        TicketLifecycleEvent::dispatch($to === Ticket::STATUS_CLOSED ? 'ticket.closed' : 'ticket.reopened', $ticket, $customer);

        return $ticket;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function escalate(User $actor, int $ticketId, array $payload): Ticket
    {
        $ticket = $this->findStaffTicket($actor, $ticketId);
        $previousDepartment = $ticket->assigned_department;
        $previousAssignee = $ticket->assigned_to;

        TicketEscalation::query()->create([
            'ticket_id' => $ticket->id,
            'escalated_from' => $ticket->assigned_department,
            'escalated_to' => $payload['escalated_to'],
            'reason' => $payload['reason'],
        ]);

        $assignedTo = array_key_exists('assigned_to', $payload)
            ? $payload['assigned_to']
            : $this->selectAssigneeId($payload['escalated_to'], $ticket->category->default_assignee_role, $ticket);

        $ticket = $this->changeStatus($ticket, Ticket::STATUS_ESCALATED, $actor, $payload['reason'], [
            'assigned_department' => $payload['escalated_to'],
            'assigned_to' => $assignedTo,
            'sla_due_at' => now()->addMinutes((int) $ticket->category->sla_minutes),
        ]);

        TicketLifecycleEvent::dispatch('ticket.escalated', $ticket, $actor, [
            'escalated_to' => $payload['escalated_to'],
            'assigned_to' => $assignedTo,
        ]);
        TicketLifecycleEvent::dispatch('ticket.assigned', $ticket, $actor, [
            'department' => $payload['escalated_to'],
            'assigned_to' => $assignedTo,
        ]);
        $this->recordAssignmentHistory($ticket, $actor, $previousDepartment, $previousAssignee, TicketAssignmentHistory::METHOD_ESCALATION, $payload['reason'] ?? null);

        return $ticket;
    }

    public function overrideStatus(User $actor, int $ticketId, string $status, string $remarks): Ticket
    {
        $ticket = $this->findStaffTicket($actor, $ticketId);

        return $this->changeStatus($ticket, $status, $actor, $remarks);
    }

    public function close(User $actor, int $ticketId, ?string $remarks): Ticket
    {
        $ticket = $this->findStaffTicket($actor, $ticketId);

        if (! $this->hasConfirmingResolutionFeedback($ticket)) {
            throw new HttpException(422, 'Close is only allowed after verification confirms the concern is resolved.');
        }

        $ticket = $this->changeStatus($ticket, Ticket::STATUS_CLOSED, $actor, (string) ($remarks ?: 'Ticket closed.'));
        TicketLifecycleEvent::dispatch('ticket.closed', $ticket, $actor);

        return $ticket;
    }

    /**
     * Reassign an open ticket without changing the flowchart status unless it is still new.
     *
     * @param  array<string, mixed>  $payload
     */
    public function reassign(User $actor, int $ticketId, array $payload): Ticket
    {
        $ticket = $this->findStaffTicket($actor, $ticketId);

        if (in_array($ticket->status, [Ticket::STATUS_CLOSED, Ticket::STATUS_RESOLVED], true)) {
            throw new HttpException(422, 'Closed tickets cannot be reassigned.');
        }

        $previousDepartment = $ticket->assigned_department;
        $previousAssignee = $ticket->assigned_to;
        $department = (string) ($payload['assigned_department'] ?? $ticket->assigned_department ?? '');
        if ($department === '') {
            throw new HttpException(422, 'assigned_department is required.');
        }

        $assignedTo = array_key_exists('assigned_to', $payload)
            ? $payload['assigned_to']
            : $this->selectAssigneeId($department, $ticket->category?->default_assignee_role, $ticket);

        $status = $ticket->status === Ticket::STATUS_NEW
            ? Ticket::STATUS_ASSIGNED
            : $ticket->status;

        $ticket = $this->advanceAlongPath(
            $ticket,
            $status,
            $actor,
            (string) ($payload['remarks'] ?? 'Ticket reassigned.'),
            [
                'assigned_department' => $department,
                'assigned_to' => $assignedTo,
            ]
        );

        TicketLifecycleEvent::dispatch('ticket.assigned', $ticket, $actor, [
            'department' => $department,
            'assigned_to' => $assignedTo,
        ]);
        $this->recordAssignmentHistory(
            $ticket,
            $actor,
            $previousDepartment,
            $previousAssignee,
            array_key_exists('assigned_to', $payload) ? TicketAssignmentHistory::METHOD_MANUAL : TicketAssignmentHistory::METHOD_AUTOMATIC,
            $payload['remarks'] ?? 'Ticket reassigned.',
        );

        return $ticket;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function staffAnalytics(User $actor, array $filters = []): array
    {
        $from = isset($filters['from']) ? Carbon::parse((string) $filters['from'])->startOfDay() : now()->startOfMonth();
        $to = isset($filters['to']) ? Carbon::parse((string) $filters['to'])->endOfDay() : now()->endOfDay();

        $tickets = $this->staffVisibleQuery($actor)
            ->with(['category', 'assignee:id,name,department_code', 'escalations', 'feedback', 'statusHistory', 'actions'])
            ->whereBetween('created_at', [$from, $to])
            ->get();

        $byCategory = [];
        foreach (TicketCategory::query()->orderBy('id')->get() as $category) {
            $subset = $tickets->where('category_id', $category->id);
            $closed = $subset->filter(fn (Ticket $ticket) => in_array($ticket->status, [Ticket::STATUS_CLOSED, Ticket::STATUS_RESOLVED], true));
            $resolutionMinutes = $closed->map(function (Ticket $ticket) {
                $closedAt = $ticket->statusHistory
                    ->where('to_status', Ticket::STATUS_CLOSED)
                    ->sortBy('id')
                    ->first()?->created_at ?? $ticket->updated_at;

                return $closedAt && $ticket->created_at
                    ? $ticket->created_at->diffInMinutes($closedAt)
                    : null;
            })->filter();

            $escalatedCount = $subset->filter(fn (Ticket $ticket) => $ticket->escalations->isNotEmpty())->count();

            $byCategory[] = [
                'category_id' => $category->id,
                'name' => $category->name,
                'department_code' => $category->department_code,
                'sla_minutes' => (int) $category->sla_minutes,
                'ticket_count' => $subset->count(),
                'closed_count' => $closed->count(),
                'avg_resolution_minutes' => $resolutionMinutes->isEmpty() ? null : round((float) $resolutionMinutes->avg(), 1),
                'escalation_count' => $escalatedCount,
                'escalation_rate' => $subset->count() === 0 ? 0 : round(($escalatedCount / $subset->count()) * 100, 1),
            ];
        }

        $departments = $tickets->groupBy(fn (Ticket $ticket) => $ticket->assigned_department ?: 'Unassigned')
            ->map(function ($group, $dept) {
                $overdue = $group->filter(function (Ticket $ticket) {
                    return $ticket->sla_due_at
                        && $ticket->sla_due_at->isPast()
                        && ! in_array($ticket->status, [Ticket::STATUS_CLOSED, Ticket::STATUS_RESOLVED], true);
                })->count();

                $actionMinutes = $group->flatMap->actions->sum('minutes_taken');

                return [
                    'department' => $dept,
                    'ticket_count' => $group->count(),
                    'closed_count' => $group->filter(fn (Ticket $t) => in_array($t->status, [Ticket::STATUS_CLOSED, Ticket::STATUS_RESOLVED], true))->count(),
                    'overdue_count' => $overdue,
                    'action_minutes' => (int) $actionMinutes,
                ];
            })
            ->values()
            ->all();

        $csr = $tickets
            ->filter(fn (Ticket $ticket) => is_numeric($ticket->created_by))
            ->groupBy('created_by')
            ->map(function ($group, $userId) {
                $actor = User::query()->find($userId);

                return [
                    'user_id' => (int) $userId,
                    'name' => $actor?->name ?? 'Staff #'.$userId,
                    'intake_count' => $group->count(),
                    'verified_count' => TicketFeedback::query()
                        ->where('verified_by', $userId)
                        ->whereIn('ticket_id', $group->pluck('id'))
                        ->count(),
                ];
            })
            ->values()
            ->all();

        $total = $tickets->count();
        $escalated = $tickets->filter(fn (Ticket $ticket) => $ticket->escalations->isNotEmpty())->count();

        return [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'totals' => [
                'tickets' => $total,
                'open' => $tickets->filter(fn (Ticket $t) => ! in_array($t->status, [Ticket::STATUS_CLOSED, Ticket::STATUS_RESOLVED], true))->count(),
                'closed' => $tickets->filter(fn (Ticket $t) => in_array($t->status, [Ticket::STATUS_CLOSED, Ticket::STATUS_RESOLVED], true))->count(),
                'escalated' => $escalated,
                'escalation_rate' => $total === 0 ? 0 : round(($escalated / $total) * 100, 1),
            ],
            'by_category' => $byCategory,
            'by_department' => $departments,
            'by_csr' => $csr,
        ];
    }

    public function hasConfirmingResolutionFeedback(Ticket $ticket): bool
    {
        $ticket->loadMissing('feedback');

        return $ticket->feedback
            ->sortByDesc('id')
            ->contains(function (TicketFeedback $feedback) {
                return $feedback->customer_confirmed && ! $feedback->needs_further_action;
            });
    }

    public function checkSlaBreaches(): int
    {
        $count = 0;

        Ticket::query()
            ->with('category')
            ->whereIn('status', [Ticket::STATUS_ASSIGNED, Ticket::STATUS_ESCALATED, Ticket::STATUS_REOPENED])
            ->whereNotNull('sla_due_at')
            ->where('sla_due_at', '<=', now())
            ->orderBy('sla_due_at')
            ->get()
            ->each(function (Ticket $ticket) use (&$count): void {
                $count += $this->processSlaBreach($ticket);
            });

        return $count;
    }

    public function addAttachment(User $actor, int $ticketId, UploadedFile $file, bool $customerOwned = false): TicketAttachment
    {
        $ticket = $customerOwned ? $this->showCustomer($actor, $ticketId) : $this->findStaffTicket($actor, $ticketId);

        if (method_exists($file, 'getMimeType') && $file->getMimeType() === null) {
            throw new HttpException(422, 'Attachment type could not be detected.');
        }

        Log::info('tickets.attachment_scan_skipped', [
            'ticket_id' => $ticket->id,
            'file_name' => $file->getClientOriginalName(),
        ]);

        $path = $file->store(
            trim((string) config('tickets.attachment.directory', 'tickets')).'/'.$ticket->id,
            config('tickets.attachment.disk', 'local')
        );

        return TicketAttachment::query()->create([
            'ticket_id' => $ticket->id,
            'uploaded_by' => $actor->id,
            'file_path' => $path,
            'file_type' => (string) $file->getMimeType(),
            'file_size' => (int) $file->getSize(),
        ]);
    }

    public function downloadAttachment(User $actor, int $attachmentId): array
    {
        $attachment = TicketAttachment::query()->with('ticket')->findOrFail($attachmentId);
        $ticket = $attachment->ticket;

        $allowed = (int) $ticket->customer_id === (int) $actor->id
            || $actor->canManageTickets()
                && ($actor->isTicketAdmin()
                    || $actor->isTicketSupervisor()
                    || $actor->isTicketCsr()
                    || $ticket->assigned_to === $actor->id
                    || ($ticket->assigned_department !== null && $ticket->assigned_department === $actor->department_code));

        if (! $allowed) {
            throw new HttpException(403, 'You are not allowed to access this attachment.');
        }

        return [
            'disk' => config('tickets.attachment.disk', 'local'),
            'path' => $attachment->file_path,
            'name' => basename($attachment->file_path),
            'mime' => $attachment->file_type,
        ];
    }

    private function createTicket(User $actor, int $customerId, array $payload): Ticket
    {
        $customer = User::query()->findOrFail($customerId);
        if (! $customer->isActiveCustomer()) {
            throw new HttpException(422, 'customer_id must be an active customer account.');
        }

        $category = TicketCategory::query()->findOrFail($payload['category_id']);

        $ticket = Ticket::query()->create([
            'ticket_no' => $this->makeTicketNo(),
            'customer_id' => $customer->id,
            'category_id' => $category->id,
            'subcategory' => $payload['subcategory'] ?? null,
            'channel' => $payload['channel'],
            'description' => $payload['description'],
            'status' => Ticket::STATUS_NEW,
            'priority' => $payload['priority'] ?? 'normal',
            'assigned_to' => null,
            'assigned_department' => null,
            'sla_due_at' => null,
            'created_by' => $payload['created_by'],
        ]);

        TicketStatusHistory::query()->create([
            'ticket_id' => $ticket->id,
            'from_status' => null,
            'to_status' => Ticket::STATUS_NEW,
            'changed_by' => $actor->id,
            'remarks' => 'Ticket created.',
            ...$this->requestAuditContext(),
        ]);

        $ticket = $this->loadTicket($ticket);
        TicketLifecycleEvent::dispatch('ticket.created', $ticket, $actor);

        return $ticket;
    }

    private function loadTicket(Ticket $ticket): Ticket
    {
        return $ticket->load([
            'category',
            'customer:id,name,email',
            'assignee:id,name,department_code',
            'attachments',
            'actions.actor:id,name',
            'feedback.verifier:id,name',
            'escalations',
            'statusHistory.actor:id,name',
        ]);
    }

    private function findStaffTicket(User $actor, int $ticketId): Ticket
    {
        /** @var Ticket|null $ticket */
        $ticket = $this->staffVisibleQuery($actor)->whereKey($ticketId)->first();
        if ($ticket) {
            return $ticket->load('category');
        }

        if (! $actor->canOpenUnassignedTickets() && Ticket::query()->whereKey($ticketId)->exists()) {
            throw new HttpException(403, 'This ticket is not assigned to you.');
        }

        throw new HttpException(404, 'Ticket not found.');
    }

    private function staffVisibleQuery(User $actor): Builder
    {
        return Ticket::query()
            ->when(
                ! $actor->canOpenUnassignedTickets(),
                fn (Builder $query) => $query->where('assigned_to', $actor->id)
            );
    }

    /**
     * Walk the linear ticket path so steps are not skipped (Submitted → Endorsed → Assigned → …).
     *
     * @param  array<string, mixed>  $extra
     */
    private function advanceAlongPath(Ticket $ticket, string $toStatus, User $actor, string $remarks, array $extra = []): Ticket
    {
        $path = TicketUi::timelinePath();
        $fromIndex = array_search($ticket->status, $path, true);
        $toIndex = array_search($toStatus, $path, true);

        if ($fromIndex === false || $toIndex === false || $toIndex <= $fromIndex) {
            return $this->changeStatus($ticket, $toStatus, $actor, $remarks, $extra);
        }

        for ($index = $fromIndex + 1; $index <= $toIndex; $index++) {
            $step = $path[$index];
            $isLast = $index === $toIndex;
            $ticket = $this->changeStatus(
                $ticket,
                $step,
                $actor,
                $isLast ? $remarks : $this->pathStepRemark($step),
                $extra
            );
        }

        return $ticket;
    }

    private function pathStepRemark(string $status): string
    {
        return match ($status) {
            Ticket::STATUS_ENDORSED => 'Ticket endorsed to the handling department.',
            Ticket::STATUS_ASSIGNED => 'Ticket assigned to staff.',
            Ticket::STATUS_IN_PROGRESS => 'Work started on this ticket.',
            Ticket::STATUS_AWAITING_FEEDBACK => 'Action logged. Waiting for feedback.',
            Ticket::STATUS_RESOLVED => 'Ticket marked resolved.',
            Ticket::STATUS_CLOSED => 'Ticket closed.',
            default => 'Ticket moved to the next step.',
        };
    }

    private function changeStatus(Ticket $ticket, string $toStatus, User $actor, string $remarks, array $extra = []): Ticket
    {
        return DB::transaction(function () use ($ticket, $toStatus, $actor, $remarks, $extra) {
            $fresh = Ticket::query()->whereKey($ticket->id)->lockForUpdate()->firstOrFail();
            $from = $fresh->status;

            $fresh->fill($extra);
            $fresh->status = $toStatus;
            $fresh->save();

            TicketStatusHistory::query()->create([
                'ticket_id' => $fresh->id,
                'from_status' => $from,
                'to_status' => $toStatus,
                'changed_by' => $actor->id,
                'remarks' => $remarks,
                ...$this->requestAuditContext(),
            ]);

            if ($toStatus !== Ticket::STATUS_ESCALATED) {
                TicketEscalation::query()
                    ->where('ticket_id', $fresh->id)
                    ->whereNull('resolved_at')
                    ->update(['resolved_at' => now()]);
            }

            return $this->loadTicket($fresh);
        });
    }

    /**
     * @return array{ip_address: string|null, user_agent: string|null}
     */
    private function requestAuditContext(): array
    {
        $request = request();

        return [
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
        ];
    }

    private function makeTicketNo(): string
    {
        return 'TKT-'.now()->format('YmdHis').'-'.Str::upper(Str::random(5));
    }

    private function resolveDepartmentForCategory(TicketCategory $category, ?Carbon $at = null): string
    {
        return $this->authoritativeDepartmentFor($category, $at);
    }

    /**
     * Deterministic department for a category (authoritative for routing).
     * AI recommendations must be validated against this.
     */
    public function authoritativeDepartmentFor(TicketCategory $category, ?Carbon $at = null): string
    {
        $at ??= now();

        if ($category->name !== 'Distribution Line') {
            return $category->department_code;
        }

        $start = (int) config('tickets.assignment.day_shift_start_hour', 6);
        $end = (int) config('tickets.assignment.day_shift_end_hour', 18);
        $hour = (int) $at->format('G');

        return $hour >= $start && $hour < $end
            ? (string) config('tickets.assignment.distribution_day_department', 'COMD')
            : (string) config('tickets.assignment.distribution_night_department', 'GUARD');
    }

    private function selectAssigneeId(string $departmentCode, ?string $roleHint = null, ?Ticket $ticket = null): ?int
    {
        return $this->previewAssigneeId($departmentCode, $roleHint, $ticket);
    }

    /**
     * Preview who the current assignment strategy would pick (does not assign).
     */
    public function previewAssigneeId(string $departmentCode, ?string $roleHint = null, ?Ticket $ticket = null): ?int
    {
        $strategy = (string) (AccessSetting::getValue('assignment_strategy') ?: config('tickets.assignment.strategy', 'round_robin'));
        if ($strategy === 'manual') {
            return null;
        }

        if ($strategy === 'ai_recommended' && $ticket) {
            $analysis = TicketAiAnalysis::query()->where('ticket_id', $ticket->id)->latest('id')->first();
            $suggested = $analysis?->suggested_assignee_id;
            if ($suggested && $this->isAssignableCandidate((int) $suggested, $departmentCode, $ticket)) {
                return (int) $suggested;
            }
            $strategy = 'round_robin';
        }

        $candidates = $this->assignableCandidates($departmentCode, $roleHint, $ticket);
        if ($candidates->isEmpty()) {
            $candidates = $this->assignableCandidates($departmentCode, null, $ticket);
        }
        if ($candidates->isEmpty()) {
            return null;
        }

        if ($strategy === 'least_busy' || $strategy === 'skill') {
            return Ticket::query()
                ->selectRaw('assigned_to, COUNT(*) as open_count')
                ->whereIn('assigned_to', $candidates->pluck('id'))
                ->whereNotIn('status', [Ticket::STATUS_CLOSED])
                ->groupBy('assigned_to')
                ->orderBy('open_count')
                ->orderBy('assigned_to')
                ->value('assigned_to') ?? $candidates->first()->id;
        }

        $lastAssigned = Ticket::query()
            ->where('assigned_department', $departmentCode)
            ->whereNotNull('assigned_to')
            ->orderByDesc('id')
            ->value('assigned_to');

        if ($lastAssigned === null) {
            return $candidates->first()->id;
        }

        $next = $candidates->first(fn (User $candidate) => $candidate->id > (int) $lastAssigned);

        return $next?->id ?? $candidates->first()->id;
    }

    /**
     * @return \Illuminate\Support\Collection<int, User>
     */
    private function assignableCandidates(string $departmentCode, ?string $roleHint = null, ?Ticket $ticket = null)
    {
        $maxWorkload = (int) (AccessSetting::getValue('max_workload') ?: config('access.max_workload', 25));
        $blockUnavailable = (bool) (AccessSetting::getValue('availability_blocks_assignment') ?? config('access.availability_blocks_assignment', true));
        $allowedAvailability = config('access.assignable_availability', ['available', 'busy']);

        $department = Department::query()->where('code', $departmentCode)->first();
        if ($department && ! $department->isActive()) {
            return collect();
        }

        $query = User::query()
            ->where('department_code', $departmentCode)
            ->where(function (Builder $inner) {
                $inner->whereNull('account_status')
                    ->orWhere('account_status', User::STATUS_ACTIVE);
            })
            ->where(function (Builder $inner) {
                $inner->whereNull('locked_until')
                    ->orWhere('locked_until', '<', now());
            })
            ->whereNotIn('role', array_merge(
                config('tickets.supervisor_roles', []),
                config('tickets.admin_roles', []),
                config('tickets.csr_roles', [])
            ))
            ->when($roleHint !== null && $roleHint !== '', function (Builder $query) use ($roleHint) {
                $query->where(function (Builder $inner) use ($roleHint) {
                    $inner->where('role', $roleHint)->orWhereNull('role');
                });
            })
            ->orderBy('id');

        $candidates = $query->get();

        return $candidates->filter(function (User $candidate) use ($maxWorkload, $blockUnavailable, $allowedAvailability, $ticket) {
            if ($blockUnavailable && $candidate->availability_status && ! in_array($candidate->availability_status, $allowedAvailability, true) && $candidate->availability_status !== 'offline') {
                if (in_array($candidate->availability_status, ['away', 'on_leave'], true)) {
                    return false;
                }
            }
            if ($candidate->staffAvailability?->on_leave) {
                return false;
            }
            $open = Ticket::query()
                ->where('assigned_to', $candidate->id)
                ->whereNotIn('status', [Ticket::STATUS_CLOSED, Ticket::STATUS_RESOLVED])
                ->count();
            if ($open >= $maxWorkload) {
                return false;
            }
            if ($ticket && $candidate->skills()->exists()) {
                $skill = $this->skillForCategory($ticket->category);
                if ($skill && ! $candidate->skills()->where('skill', $skill)->exists()) {
                    return false;
                }
            }

            return true;
        })->values();
    }

    private function isAssignableCandidate(int $userId, string $departmentCode, ?Ticket $ticket = null): bool
    {
        return $this->assignableCandidates($departmentCode, null, $ticket)->contains(fn (User $user) => $user->id === $userId);
    }

    private function skillForCategory(?TicketCategory $category): ?string
    {
        $code = strtoupper((string) $category?->department_code);

        return match (true) {
            str_contains($code, 'CCAD') => 'billing',
            str_contains($code, 'AO') => 'metering',
            str_contains($code, 'COMD') => 'distribution',
            str_contains($code, 'TSD') => 'transmission',
            default => null,
        };
    }

    private function recordAssignmentHistory(
        Ticket $ticket,
        ?User $actor,
        ?string $previousDepartment,
        ?int $previousAssignee,
        string $method,
        ?string $reason = null,
    ): void {
        if (! \Illuminate\Support\Facades\Schema::hasTable('ticket_assignment_history')) {
            return;
        }

        $analysis = TicketAiAnalysis::query()->where('ticket_id', $ticket->id)->latest('id')->first();

        TicketAssignmentHistory::query()->create([
            'ticket_id' => $ticket->id,
            'previous_department' => $previousDepartment,
            'new_department' => $ticket->assigned_department,
            'previous_assignee_id' => $previousAssignee,
            'new_assignee_id' => $ticket->assigned_to,
            'method' => $method,
            'assigned_by' => $actor?->id,
            'reason' => $reason,
            'ai_recommendation' => $analysis?->suggested_department,
            'ai_confidence' => $analysis?->confidence,
            'created_at' => now(),
        ]);
    }

    private function escalateToTsd(Ticket $ticket, User $actor, string $reason): Ticket
    {
        $tsdDepartment = (string) config('tickets.assignment.tsd_department', 'TSD');

        return $this->escalate($actor, $ticket->id, [
            'escalated_to' => $tsdDepartment,
            'reason' => $reason,
        ]);
    }

    private function processSlaBreach(Ticket $ticket): int
    {
        $openEscalation = TicketEscalation::query()
            ->where('ticket_id', $ticket->id)
            ->whereNull('resolved_at')
            ->latest('id')
            ->first();

        if ($openEscalation === null) {
            $actor = $this->pickSupervisorActor($ticket->assigned_department);
            $this->escalate($actor, $ticket->id, [
                'escalated_to' => (string) $ticket->assigned_department,
                'assigned_to' => $ticket->assigned_to,
                'reason' => 'SLA breached. Supervisor notified.',
            ]);

            return 1;
        }

        $threshold = (int) config('tickets.escalation.second_tier_after_minutes', 30);
        $secondTierAt = Carbon::parse($ticket->sla_due_at)->addMinutes($threshold);
        if ($secondTierAt->isFuture()) {
            return 0;
        }

        $secondTierDepartment = (string) config('tickets.escalation.second_tier_department', 'AREA-ADMIN');
        $exists = TicketEscalation::query()
            ->where('ticket_id', $ticket->id)
            ->where('escalated_to', $secondTierDepartment)
            ->exists();

        if ($exists) {
            return 0;
        }

        $actor = $this->pickSupervisorActor($secondTierDepartment);
        $this->escalate($actor, $ticket->id, [
            'escalated_to' => $secondTierDepartment,
            'reason' => 'Second-tier SLA breach. Area/regional admin notified.',
        ]);

        return 1;
    }

    private function pickSupervisorActor(?string $departmentCode): User
    {
        $supervisor = User::query()
            ->where('department_code', $departmentCode)
            ->whereIn('role', config('tickets.supervisor_roles', []))
            ->orderBy('id')
            ->first();

        if ($supervisor) {
            return $supervisor;
        }

        $admin = User::query()
            ->whereIn('role', array_merge(
                config('tickets.supervisor_roles', []),
                config('tickets.admin_roles', []),
                config('tickets.csr_roles', [])
            ))
            ->orderBy('id')
            ->first();

        if (! $admin) {
            throw new HttpException(422, 'No supervisor or administrator is available for SLA escalation.');
        }

        return $admin;
    }
}
