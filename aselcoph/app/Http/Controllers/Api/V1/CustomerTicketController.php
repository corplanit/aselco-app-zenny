<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreCustomerTicketRequest;
use App\Http\Requests\Api\V1\UploadTicketAttachmentRequest;
use App\Models\Ticket;
use App\Services\TicketService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

class CustomerTicketController extends Controller
{
    public function __construct(private TicketService $tickets)
    {
    }

    public function store(StoreCustomerTicketRequest $request): JsonResponse
    {
        $ticket = $this->tickets->createCustomerTicket($request->user(), $request->validated() + ['channel' => 'app']);

        return response()->json($this->shape($ticket), 201);
    }

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['nullable', 'in:new,endorsed,assigned,in_progress,awaiting_feedback,escalated,resolved,closed,reopened'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        return response()->json($this->tickets->listCustomer($request->user(), $validated));
    }

    public function show(Request $request, int $id): JsonResponse
    {
        return response()->json($this->shape($this->tickets->showCustomer($request->user(), $id)));
    }

    public function attachments(UploadTicketAttachmentRequest $request, int $id): JsonResponse
    {
        $attachment = $this->tickets->addAttachment($request->user(), $id, $request->file('attachment'), true);

        return response()->json([
            'data' => [
                'id' => $attachment->id,
                'file_type' => $attachment->file_type,
                'file_size' => $attachment->file_size,
                'uploaded_at' => $attachment->uploaded_at?->toIso8601String(),
                'download_url' => URL::temporarySignedRoute(
                    'api.v1.tickets.attachments.download',
                    now()->addMinutes((int) config('tickets.attachment.signed_url_ttl_minutes', 15)),
                    ['attachment' => $attachment->id]
                ),
            ],
        ], 201);
    }

    public function feedback(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'method' => ['required', 'in:call,message'],
            'customer_confirmed' => ['required', 'boolean'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        return response()->json($this->shape($this->tickets->addCustomerFeedback($request->user(), $id, $validated)));
    }

    private function shape(Ticket $ticket): array
    {
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
            'attachments' => $ticket->attachments->map(fn ($attachment) => [
                'id' => $attachment->id,
                'file_type' => $attachment->file_type,
                'file_size' => $attachment->file_size,
                'uploaded_at' => $attachment->uploaded_at?->toIso8601String(),
                'download_url' => URL::temporarySignedRoute(
                    'api.v1.tickets.attachments.download',
                    now()->addMinutes((int) config('tickets.attachment.signed_url_ttl_minutes', 15)),
                    ['attachment' => $attachment->id]
                ),
            ])->values(),
            'actions' => $ticket->actions->map(fn ($action) => [
                'id' => $action->id,
                'actor_role' => $action->actor_role,
                // Hide internal staff notes from customer timeline.
                'action_taken' => null,
                'minutes_taken' => (int) $action->minutes_taken,
                'requires_payment' => (bool) $action->requires_payment,
                'requires_tsd_intervention' => (bool) $action->requires_tsd_intervention,
                'created_at' => $action->created_at?->toIso8601String(),
            ])->values(),
            'status_history' => $ticket->statusHistory->map(fn ($history) => [
                'id' => $history->id,
                'from_status' => $history->from_status,
                'to_status' => $history->to_status,
                'changed_by' => $history->changed_by,
                // Only expose remarks authored by the customer; staff notes are internal-only.
                'remarks' => (int) $history->changed_by === (int) $ticket->customer_id ? $history->remarks : null,
                'created_at' => $history->created_at?->toIso8601String(),
            ])->values(),
        ];
    }
}
