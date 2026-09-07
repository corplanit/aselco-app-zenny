<?php

namespace App\Services\Rag;

use App\Models\KnowledgeChunk;
use App\Models\KnowledgeDocument;
use App\Models\KnowledgeRetrievalLog;
use App\Models\User;

class KnowledgeRetriever
{
    public function __construct(
        private QueryProcessor $processor,
        private EmbeddingEncoder $encoder,
    ) {}

    /**
     * @return array{hits: list<RetrievalHit>, sufficient: bool, top_score: float, query_tokens: list<string>}
     */
    public function retrieve(string $question, string $channel = 'customer', ?User $user = null, ?string $ip = null): array
    {
        $processed = $this->processor->process($question);
        $limit = max(1, (int) config('rag.top_k', 4));
        $minScore = (float) config('rag.min_score', 0.18);
        $lexW = (float) config('rag.hybrid_lexical_weight', 0.6);
        $vecW = (float) config('rag.hybrid_vector_weight', 0.4);

        $queryEmbed = $this->encoder->embed($processed['normalized']);

        $chunks = KnowledgeChunk::query()
            ->with(['document.category'])
            ->where('active', true)
            ->whereHas('document', function ($query) {
                $query->where('status', KnowledgeDocument::STATUS_ACTIVE)
                    ->whereNull('deleted_at')
                    ->where(function ($q) {
                        $q->whereNull('effective_at')->orWhere('effective_at', '<=', now());
                    })
                    ->where(function ($q) {
                        $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
                    });
            })
            ->get();

        $ranked = $chunks->map(function (KnowledgeChunk $chunk) use ($processed, $queryEmbed, $lexW, $vecW) {
            $lex = $this->lexicalScore($processed['tokens'], $chunk);
            $vec = is_array($chunk->embedding) ? $this->encoder->cosine($queryEmbed, $chunk->embedding) : 0.0;
            $titleBoost = $this->titleBoost($processed['tokens'], (string) $chunk->document?->title);

            return [
                'chunk' => $chunk,
                'score' => ($lexW * $lex) + ($vecW * $vec) + $titleBoost,
            ];
        })
            ->filter(fn (array $row) => $row['score'] >= $minScore)
            ->sortByDesc('score')
            ->take($limit)
            ->values();

        $hits = $ranked->map(function (array $row) {
            /** @var KnowledgeChunk $chunk */
            $chunk = $row['chunk'];
            $document = $chunk->document;

            return new RetrievalHit(
                chunkId: $chunk->id,
                documentId: (int) $document?->id,
                title: (string) $document?->title,
                content: $chunk->content,
                score: (float) $row['score'],
                metadata: [
                    'category' => $document?->category?->slug,
                    'category_name' => $document?->category?->name,
                    'department' => $document?->department,
                    'service_type' => $document?->service_type,
                    'version' => $document?->current_version,
                    'source' => $document?->source,
                    'status' => $document?->status,
                ],
            );
        })->all();

        $top = $hits[0]->score ?? 0.0;
        $sufficient = $hits !== [] && $top >= $minScore;

        $this->log($user?->id, $channel, $processed['normalized'], $hits, $sufficient, $top, $ip);

        return [
            'hits' => $hits,
            'sufficient' => $sufficient,
            'top_score' => $top,
            'query_tokens' => $processed['tokens'],
        ];
    }

    /**
     * @param  list<RetrievalHit>  $hits
     */
    public function buildContext(array $hits): string
    {
        if ($hits === []) {
            return '';
        }

        $parts = [];
        foreach ($hits as $i => $hit) {
            $n = $i + 1;
            $parts[] = "[Source {$n}: {$hit->title} | {$hit->metadata['category_name']} v{$hit->metadata['version']}]\n{$hit->content}";
        }

        return implode("\n\n", $parts);
    }

    /**
     * @param  list<string>  $queryTokens
     */
    private function lexicalScore(array $queryTokens, KnowledgeChunk $chunk): float
    {
        if ($queryTokens === []) {
            return 0.0;
        }

        $chunkTokens = is_array($chunk->tokens) ? $chunk->tokens : [];
        if ($chunkTokens === []) {
            return 0.0;
        }

        $overlap = count(array_intersect($queryTokens, $chunkTokens));

        return $overlap / count($queryTokens);
    }

    /**
     * @param  list<string>  $queryTokens
     */
    private function titleBoost(array $queryTokens, string $title): float
    {
        if ($title === '' || $queryTokens === []) {
            return 0.0;
        }

        $titleTokens = $this->processor->tokenize($title);
        $overlap = count(array_intersect($queryTokens, $titleTokens));

        return min(0.15, $overlap * 0.05);
    }

    /**
     * @param  list<RetrievalHit>  $hits
     */
    private function log(?int $userId, string $channel, string $normalized, array $hits, bool $sufficient, float $top, ?string $ip): void
    {
        KnowledgeRetrievalLog::query()->create([
            'user_id' => $userId,
            'channel' => $channel,
            'query_hash' => hash('sha256', $normalized),
            'query_chars' => min(mb_strlen($normalized), 65535),
            'hit_count' => count($hits),
            'top_score' => round($top, 4),
            'sufficient' => $sufficient,
            'hit_ids' => array_map(fn (RetrievalHit $hit) => $hit->chunkId, $hits),
            'ip_address' => $ip,
            'created_at' => now(),
        ]);
    }
}
