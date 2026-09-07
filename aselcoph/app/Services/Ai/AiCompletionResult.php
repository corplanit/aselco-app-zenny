<?php

namespace App\Services\Ai;

class AiCompletionResult
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public readonly array $payload,
        public readonly string $model,
        public readonly ?int $promptTokens,
        public readonly ?int $completionTokens,
        public readonly int $latencyMs,
        public readonly bool $fromProvider,
    ) {}
}
