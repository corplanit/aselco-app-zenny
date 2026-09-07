<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\CustomerAiChatRequest;
use App\Http\Requests\Api\V1\CustomerAiEscalateRequest;
use App\Http\Requests\Api\V1\CustomerAiInquiryRequest;
use App\Http\Requests\Api\V1\KnowledgeSearchRequest;
use App\Services\Ai\AiAssistantService;
use App\Services\Ai\ConversationCoach;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerAiController extends Controller
{
    public function __construct(
        private AiAssistantService $assistant,
        private ConversationCoach $coach,
    ) {
    }

    public function chat(CustomerAiChatRequest $request): JsonResponse
    {
        $validated = $request->validated();

        return response()->json($this->assistant->chat(
            $request->user(),
            $validated['message'],
            isset($validated['conversation_id']) ? (int) $validated['conversation_id'] : null,
            $request->ip(),
        ));
    }

    public function inquiry(CustomerAiInquiryRequest $request): JsonResponse
    {
        return response()->json($this->assistant->inquiry(
            $request->user(),
            $request->validated('message'),
            $request->ip(),
        ));
    }

    public function search(KnowledgeSearchRequest $request): JsonResponse
    {
        if ($request->filled('limit')) {
            config(['rag.top_k' => (int) $request->integer('limit')]);
        }

        return response()->json($this->assistant->searchKnowledge(
            $request->user(),
            $request->validated('query'),
            $request->ip(),
        ));
    }

    public function conversations(Request $request): JsonResponse
    {
        $perPage = min(50, max(1, (int) $request->integer('per_page', 20)));

        return response()->json($this->assistant->listConversations($request->user(), $perPage));
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $conversation = $this->assistant->showConversation($request->user(), $id);

        if ($conversation === null) {
            return response()->json([
                'message' => 'Conversation not found.',
                'code' => 'NOT_FOUND',
            ], 404);
        }

        return response()->json($conversation);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        if (! $this->assistant->deleteConversation($request->user(), $id)) {
            return response()->json([
                'message' => 'Conversation not found.',
                'code' => 'NOT_FOUND',
            ], 404);
        }

        return response()->json([
            'message' => 'Conversation deleted.',
            'code' => 'CONVERSATION_DELETED',
            'suggested_questions' => $this->coach->suggestedQuestions(),
        ]);
    }

    public function escalate(CustomerAiEscalateRequest $request): JsonResponse
    {
        $result = $this->assistant->escalate(
            $request->user(),
            $request->validated(),
            $request->ip(),
        );

        if (($result['escalated'] ?? false) !== true) {
            $status = ($result['code'] ?? '') === 'NOT_FOUND' ? 404 : 422;

            return response()->json($result, $status);
        }

        return response()->json($result, 201);
    }

    public function bootstrap(): JsonResponse
    {
        return response()->json([
            'welcome' => [
                "Hello! I'm your ASELCO customer service assistant.",
                'I can help with billing, services, FAQs, and complaint guidance using approved knowledge. I cannot change bills, wallets, or tickets — I guide you into the official workflows.',
            ],
            'suggested_questions' => $this->coach->suggestedQuestions(),
            'actions' => $this->coach->actions('none', false),
            'capabilities' => [
                'billing_assistance',
                'service_information',
                'faq',
                'complaint_guidance',
                'inquiry_assistance',
                'human_escalation',
            ],
            'limits' => [
                'can_mutate_billing' => false,
                'can_mutate_tickets' => false,
                'can_process_payments' => false,
            ],
        ]);
    }
}
