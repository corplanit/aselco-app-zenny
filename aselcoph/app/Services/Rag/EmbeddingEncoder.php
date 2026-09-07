<?php

namespace App\Services\Rag;

use App\Services\Ai\AiProviderException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EmbeddingEncoder
{
    public function __construct(private QueryProcessor $processor)
    {
    }

    public function openaiConfigured(): bool
    {
        return (string) config('rag.embedding.provider') === 'openai'
            && filled(config('ai.openai.api_key'));
    }

    /**
     * @return list<float>
     */
    public function embed(string $text): array
    {
        if ($this->openaiConfigured()) {
            try {
                return $this->embedOpenAi($text);
            } catch (AiProviderException $e) {
                Log::warning('rag.embedding.fallback', ['code' => $e->errorCode]);
            }
        }

        return $this->embedLocal($text);
    }

    /**
     * @param  list<string>  $texts
     * @return list<list<float>>
     */
    public function embedMany(array $texts): array
    {
        return array_map(fn (string $text) => $this->embed($text), $texts);
    }

    public function modelName(): string
    {
        if ($this->openaiConfigured()) {
            return (string) config('rag.embedding.model');
        }

        return 'local-hash-'.(int) config('rag.embedding.local_dimensions', 128);
    }

    /**
     * @param  list<float>  $a
     * @param  list<float>  $b
     */
    public function cosine(array $a, array $b): float
    {
        $n = min(count($a), count($b));
        if ($n === 0) {
            return 0.0;
        }

        $dot = 0.0;
        $na = 0.0;
        $nb = 0.0;
        for ($i = 0; $i < $n; $i++) {
            $dot += $a[$i] * $b[$i];
            $na += $a[$i] * $a[$i];
            $nb += $b[$i] * $b[$i];
        }

        if ($na <= 0.0 || $nb <= 0.0) {
            return 0.0;
        }

        return $dot / (sqrt($na) * sqrt($nb));
    }

    /**
     * @return list<float>
     */
    public function embedLocal(string $text): array
    {
        $dim = max(32, (int) config('rag.embedding.local_dimensions', 128));
        $vector = array_fill(0, $dim, 0.0);
        $tokens = $this->processor->tokenize($text);

        foreach ($tokens as $token) {
            $bucket = abs(crc32($token)) % $dim;
            $sign = (abs(crc32('s'.$token)) % 2) === 0 ? 1.0 : -1.0;
            $vector[$bucket] += $sign;
        }

        return $this->l2($vector);
    }

    /**
     * @return list<float>
     */
    private function embedOpenAi(string $text): array
    {
        try {
            $response = Http::baseUrl(rtrim((string) config('ai.openai.base_url'), '/'))
                ->withToken((string) config('ai.openai.api_key'))
                ->acceptJson()
                ->timeout((int) config('rag.embedding.timeout', 20))
                ->retry((int) config('rag.embedding.retries', 2), 250, throw: false)
                ->post('/embeddings', [
                    'model' => (string) config('rag.embedding.model'),
                    'input' => mb_substr($text, 0, 8000),
                ]);
        } catch (ConnectionException $e) {
            throw new AiProviderException('Embedding request timed out.', 'RAG_EMBED_TIMEOUT', 0, $e);
        }

        if (! $response->successful()) {
            throw new AiProviderException('Embedding provider error.', 'RAG_EMBED_HTTP_'.$response->status());
        }

        $vector = data_get($response->json(), 'data.0.embedding');
        if (! is_array($vector) || $vector === []) {
            throw new AiProviderException('Invalid embedding payload.', 'RAG_EMBED_INVALID');
        }

        return array_map(static fn ($v) => (float) $v, $vector);
    }

    /**
     * @param  list<float>  $vector
     * @return list<float>
     */
    private function l2(array $vector): array
    {
        $sum = 0.0;
        foreach ($vector as $v) {
            $sum += $v * $v;
        }
        if ($sum <= 0.0) {
            return $vector;
        }
        $norm = sqrt($sum);

        return array_map(static fn (float $v) => $v / $norm, $vector);
    }
}
