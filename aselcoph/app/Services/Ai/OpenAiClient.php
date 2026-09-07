<?php

namespace App\Services\Ai;

use App\Services\Ai\Contracts\AiCompletionClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenAiClient implements AiCompletionClient
{
    public function isConfigured(): bool
    {
        return (bool) config('ai.enabled')
            && filled(config('ai.openai.api_key'));
    }

    public function completeJson(string $systemPrompt, array $messages): AiCompletionResult
    {
        if (! $this->isConfigured()) {
            throw new AiProviderException('OpenAI is not configured.', 'AI_NOT_CONFIGURED');
        }

        $started = microtime(true);
        $model = (string) config('ai.openai.model');
        $payload = [
            'model' => $model,
            'temperature' => (float) config('ai.openai.temperature', 0.3),
            'max_tokens' => (int) config('ai.openai.max_tokens', 500),
            'response_format' => ['type' => 'json_object'],
            'messages' => array_merge(
                [['role' => 'system', 'content' => $systemPrompt]],
                $messages
            ),
        ];

        try {
            $response = Http::baseUrl(rtrim((string) config('ai.openai.base_url'), '/'))
                ->withToken((string) config('ai.openai.api_key'))
                ->acceptJson()
                ->timeout((int) config('ai.openai.timeout', 20))
                ->retry(
                    (int) config('ai.openai.retries', 2),
                    (int) config('ai.openai.retry_ms', 250),
                    throw: false,
                )
                ->post('/chat/completions', $payload);
        } catch (ConnectionException $e) {
            Log::warning('ai.openai.timeout', ['message' => $e->getMessage()]);
            throw new AiProviderException('AI request timed out.', 'AI_TIMEOUT', 0, $e);
        }

        $latency = (int) round((microtime(true) - $started) * 1000);

        if (! $response->successful()) {
            Log::warning('ai.openai.http_error', [
                'status' => $response->status(),
                'model' => $model,
            ]);

            throw new AiProviderException('AI provider returned an error.', 'AI_HTTP_'.$response->status());
        }

        $json = $response->json();
        $content = (string) data_get($json, 'choices.0.message.content', '');
        $decoded = json_decode($content, true);

        if (! is_array($decoded)) {
            throw new AiProviderException('AI returned an invalid payload.', 'AI_INVALID_JSON');
        }

        return new AiCompletionResult(
            payload: $decoded,
            model: (string) data_get($json, 'model', $model),
            promptTokens: data_get($json, 'usage.prompt_tokens'),
            completionTokens: data_get($json, 'usage.completion_tokens'),
            latencyMs: $latency,
            fromProvider: true,
        );
    }
}
