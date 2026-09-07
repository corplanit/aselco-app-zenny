<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\AdminAiAssistRequest;
use App\Services\Ai\AiAssistantService;
use Illuminate\Http\JsonResponse;

class AdminAiController extends Controller
{
    public function __construct(private AiAssistantService $assistant)
    {
    }

    public function assist(AdminAiAssistRequest $request): JsonResponse
    {
        $validated = $request->validated();

        return response()->json($this->assistant->adminAssist(
            $request->user(),
            $validated['message'],
            isset($validated['ticket_id']) ? (int) $validated['ticket_id'] : null,
            $request->ip(),
            $request->userAgent(),
        ));
    }
}
