<?php

namespace App\Services\Ai\Contracts;

use App\Services\Ai\AiCompletionResult;

interface AiCompletionClient
{
    public function isConfigured(): bool;

    /**
     * @param  list<array{role: string, content: string}>  $messages
     */
    public function completeJson(string $systemPrompt, array $messages): AiCompletionResult;
}
