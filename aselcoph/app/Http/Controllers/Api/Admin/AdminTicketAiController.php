<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\TicketAiAnalysis;
use App\Models\TicketAiResponseDraft;
use App\Services\Ai\TicketAiAnalyzer;
use App\Services\Ai\TicketAiResponseAssistant;
use App\Services\TicketService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

class AdminTicketAiController extends Controller
{
    public function __construct(
        private TicketService $tickets,
        private TicketAiAnalyzer $analyzer,
        private TicketAiResponseAssistant $responses,
    ) {}

    public function show(Request $request, int $id): JsonResponse
    {
        $ticket = $this->tickets->showAdmin($request->user(), $id);
        $analysis = $ticket->latestAiAnalysis()->with(['recommendedCategory', 'suggestedAssignee'])->first();
        $drafts = TicketAiResponseDraft::query()
            ->where('ticket_id', $ticket->id)
            ->orderByDesc('id')
            ->limit(10)
            ->get();

        return response()->json([
            'ticket_id' => $ticket->id,
            'ticket_no' => $ticket->ticket_no,
            'customer_description' => $ticket->description,
            'analysis' => $analysis ? $this->shapeAnalysis($analysis) : null,
            'response_drafts' => $drafts->map(fn (TicketAiResponseDraft $d) => $this->shapeDraft($d))->all(),
            'routing_authoritative' => true,
            'auto_send_enabled' => (bool) config('tickets.ai.auto_send_low_risk_responses', false),
        ]);
    }

    public function analyze(Request $request, int $id): JsonResponse
    {
        $ticket = $this->tickets->showAdmin($request->user(), $id);
        $analysis = $this->analyzer->analyze($ticket, $request->user());

        return response()->json([
            'message' => 'AI analysis completed (advisory only).',
            'analysis' => $this->shapeAnalysis($analysis),
        ], 201);
    }

    public function applyPriority(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $ticket = $this->tickets->showAdmin($request->user(), $id);
        $analysis = $ticket->latestAiAnalysis;
        if ($analysis === null) {
            throw new HttpException(422, 'No AI analysis available to apply.');
        }

        $ticket = $this->analyzer->applyPriorityRecommendation(
            $request->user(),
            $ticket,
            $analysis,
            $validated['reason'] ?? null
        );

        return response()->json([
            'message' => 'Priority updated by authorized staff from AI recommendation.',
            'ticket' => [
                'id' => $ticket->id,
                'priority' => $ticket->priority,
            ],
            'analysis' => $this->shapeAnalysis($analysis->fresh()),
        ]);
    }

    public function suggestResponse(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'guidance' => ['nullable', 'string', 'max:2000'],
        ]);

        $ticket = $this->tickets->showAdmin($request->user(), $id);
        $draft = $this->responses->suggest($ticket, $request->user(), $validated['guidance'] ?? null);

        return response()->json([
            'message' => 'Suggested response ready for CSR review. Not sent to customer.',
            'draft' => $this->shapeDraft($draft),
        ], 201);
    }

    public function reviewDraft(Request $request, int $ticketId, int $draftId): JsonResponse
    {
        $validated = $request->validate([
            'decision' => ['required', 'in:approve,reject'],
            'edited_body' => ['nullable', 'string', 'max:5000'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $this->tickets->showAdmin($request->user(), $ticketId);
        $draft = TicketAiResponseDraft::query()
            ->where('ticket_id', $ticketId)
            ->whereKey($draftId)
            ->firstOrFail();

        $result = $this->responses->review(
            $request->user(),
            $draft,
            $validated['decision'],
            $validated['edited_body'] ?? null,
            $validated['notes'] ?? null,
        );

        return response()->json($result);
    }

    public function dashboard(Request $request): JsonResponse
    {
        return response()->json($this->responses->dashboardStats());
    }

    /**
     * @return array<string, mixed>
     */
    private function shapeAnalysis(TicketAiAnalysis $analysis): array
    {
        return [
            'id' => $analysis->id,
            'recommended_category' => $analysis->recommendedCategory?->only(['id', 'name', 'department_code']),
            'category_matches_submitted' => $analysis->category_matches_submitted,
            'recommended_priority' => $analysis->recommended_priority,
            'sentiment' => $analysis->sentiment,
            'confidence' => $analysis->confidence,
            'summary' => $analysis->summary,
            'suggested_department' => $analysis->suggested_department,
            'suggested_assignee' => $analysis->suggestedAssignee?->only(['id', 'name', 'department_code']),
            'recommended_next_action' => $analysis->recommended_next_action,
            'suggested_response' => $analysis->suggested_response,
            'routing_validation' => $analysis->routing_validation,
            'knowledge_citations' => $analysis->knowledge_citations,
            'model' => $analysis->model,
            'source' => $analysis->source,
            'ok' => $analysis->ok,
            'error_code' => $analysis->error_code,
            'human_override' => $analysis->human_override,
            'override_field' => $analysis->override_field,
            'override_reason' => $analysis->override_reason,
            'analyzed_at' => $analysis->analyzed_at?->toIso8601String(),
            'internal_only' => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function shapeDraft(TicketAiResponseDraft $draft): array
    {
        return [
            'id' => $draft->id,
            'ticket_id' => $draft->ticket_id,
            'draft' => $draft->draft,
            'edited_body' => $draft->edited_body,
            'status' => $draft->status,
            'confidence' => $draft->confidence,
            'requires_human_approval' => true,
            'auto_send_eligible' => $draft->auto_send_eligible,
            'auto_sent' => $draft->auto_sent,
            'reviewed_at' => $draft->reviewed_at?->toIso8601String(),
            'review_notes' => $draft->review_notes,
            'created_at' => $draft->created_at?->toIso8601String(),
        ];
    }
}
