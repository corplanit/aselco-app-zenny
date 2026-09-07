<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Admin\StoreAdminTicketRequest;
use App\Http\Requests\Api\V1\UploadTicketAttachmentRequest;
use App\Models\Ticket;
use App\Services\TicketService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

class AdminTicketController extends Controller
{
    public function __construct(private TicketService $tickets)
    {
    }

    public function store(StoreAdminTicketRequest $request): JsonResponse
    {
        $ticket = $this->tickets->createAdminTicket($request->user(), $request->validated());

        return response()->json($this->shape($ticket), 201);
    }

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['nullable', 'in:new,endorsed,assigned,in_progress,awaiting_feedback,escalated,resolved,closed,reopened'],
            'category_id' => ['nullable', 'integer', 'exists:ticket_categories,id'],
            'assigned_department' => ['nullable', 'string', 'max:40'],
            'search' => ['nullable', 'string', 'max:160'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        return response()->json($this->tickets->listAdmin($request->user(), $validated));
    }

    public function show(Request $request, int $id): JsonResponse
    {
        return response()->json($this->shape($this->tickets->showAdmin($request->user(), $id)));
    }

    public function endorse(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
            'assigned_department' => ['nullable', 'string', 'max:40'],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ]);

        return response()->json($this->shape($this->tickets->endorse($request->user(), $id, $validated)));
    }

    public function actions(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'action_taken' => ['required', 'string', 'max:5000'],
            'minutes_taken' => ['required', 'integer', 'min:0'],
            'requires_payment' => ['nullable', 'boolean'],
            'requires_tsd_intervention' => ['nullable', 'boolean'],
            'tsd_reason' => ['nullable', 'string', 'max:1000'],
        ]);

        return response()->json($this->shape($this->tickets->addAction($request->user(), $id, $validated)));
    }

    public function feedback(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'method' => ['required', 'in:call,message'],
            'customer_confirmed' => ['required', 'boolean'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'needs_further_action' => ['required', 'boolean'],
        ]);

        return response()->json($this->shape($this->tickets->addStaffFeedback($request->user(), $id, $validated)));
    }

    public function escalate(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'escalated_to' => ['required', 'string', 'max:40'],
            'reason' => ['required', 'string', 'max:5000'],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        return response()->json($this->shape($this->tickets->escalate($request->user(), $id, $validated)));
    }

    public function status(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'in:new,endorsed,assigned,in_progress,awaiting_feedback,escalated,resolved,closed,reopened'],
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        return response()->json($this->shape(
            $this->tickets->overrideStatus($request->user(), $id, $validated['status'], $validated['reason'])
        ));
    }

    public function close(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'remarks' => ['nullable', 'string', 'max:1000'],
        ]);

        return response()->json($this->shape($this->tickets->close($request->user(), $id, $validated['remarks'] ?? null)));
    }

    public function attachments(UploadTicketAttachmentRequest $request, int $id): JsonResponse
    {
        $attachment = $this->tickets->addAttachment($request->user(), $id, $request->file('attachment'));

        return response()->json(['data' => $this->shapeAttachment($attachment)], 201);
    }

    private function shape(Ticket $ticket): array
    {
        $ticket->loadMissing(['latestAiAnalysis.recommendedCategory', 'latestAiAnalysis.suggestedAssignee']);

        $analysis = $ticket->latestAiAnalysis;

        return [
            'id' => $ticket->id,
            'ticket_no' => $ticket->ticket_no,
            'customer_id' => $ticket->customer_id,
            'category' => $ticket->category?->only(['id', 'name', 'department_code', 'default_assignee_role', 'sla_minutes', 'requires_payment_check', 'requires_tsd_check']),
            'subcategory' => $ticket->subcategory,
            'channel' => $ticket->channel,
            'description' => $ticket->description,
            'status' => $ticket->status,
            'priority' => $ticket->priority,
            'assigned_to' => $ticket->assigned_to,
            'assigned_department' => $ticket->assigned_department,
            'sla_due_at' => $ticket->sla_due_at?->toIso8601String(),
            'created_by' => $ticket->created_by,
            'created_at' => $ticket->created_at?->toIso8601String(),
            'updated_at' => $ticket->updated_at?->toIso8601String(),
            'ai_analysis' => $analysis ? [
                'id' => $analysis->id,
                'recommended_category' => $analysis->recommendedCategory?->only(['id', 'name', 'department_code']),
                'category_matches_submitted' => $analysis->category_matches_submitted,
                'recommended_priority' => $analysis->recommended_priority,
                'sentiment' => $analysis->sentiment,
                'confidence' => $analysis->confidence,
                'summary' => $analysis->summary,
                'suggested_department' => $analysis->suggested_department,
                'suggested_assignee' => $analysis->suggestedAssignee?->only(['id', 'name']),
                'recommended_next_action' => $analysis->recommended_next_action,
                'routing_validation' => $analysis->routing_validation,
                'source' => $analysis->source,
                'analyzed_at' => $analysis->analyzed_at?->toIso8601String(),
                'internal_only' => true,
            ] : null,
            'attachments' => $ticket->attachments->map(fn ($attachment) => $this->shapeAttachment($attachment))->values(),
            'actions' => $ticket->actions->map(fn ($action) => [
                'id' => $action->id,
                'actor_id' => $action->actor_id,
                'actor_role' => $action->actor_role,
                'action_taken' => $action->action_taken,
                'minutes_taken' => $action->minutes_taken,
                'requires_payment' => $action->requires_payment,
                'requires_tsd_intervention' => $action->requires_tsd_intervention,
                'created_at' => $action->created_at?->toIso8601String(),
            ])->values(),
            'feedback' => $ticket->feedback->map(fn ($feedback) => [
                'id' => $feedback->id,
                'verified_by' => $feedback->verified_by,
                'method' => $feedback->method,
                'customer_confirmed' => (bool) $feedback->customer_confirmed,
                'notes' => $feedback->notes,
                'needs_further_action' => (bool) $feedback->needs_further_action,
                'created_at' => $feedback->created_at?->toIso8601String(),
            ])->values(),
            'escalations' => $ticket->escalations->map(fn ($escalation) => [
                'id' => $escalation->id,
                'escalated_from' => $escalation->escalated_from,
                'escalated_to' => $escalation->escalated_to,
                'reason' => $escalation->reason,
                'escalated_at' => $escalation->escalated_at?->toIso8601String(),
                'resolved_at' => $escalation->resolved_at?->toIso8601String(),
            ])->values(),
            'status_history' => $ticket->statusHistory->map(fn ($history) => [
                'id' => $history->id,
                'from_status' => $history->from_status,
                'to_status' => $history->to_status,
                'changed_by' => $history->changed_by,
                'remarks' => $history->remarks,
                'created_at' => $history->created_at?->toIso8601String(),
            ])->values(),
        ];
    }

    private function shapeAttachment($attachment): array
    {
        return [
            'id' => $attachment->id,
            'file_type' => $attachment->file_type,
            'file_size' => $attachment->file_size,
            'uploaded_at' => $attachment->uploaded_at?->toIso8601String(),
            'download_url' => URL::temporarySignedRoute(
                'api.v1.tickets.attachments.download',
                now()->addMinutes((int) config('tickets.attachment.signed_url_ttl_minutes', 15)),
                ['attachment' => $attachment->id]
            ),
        ];
    }
}
