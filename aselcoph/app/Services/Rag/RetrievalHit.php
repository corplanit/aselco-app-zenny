<?php

namespace App\Services\Rag;

class RetrievalHit
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public readonly int $chunkId,
        public readonly int $documentId,
        public readonly string $title,
        public readonly string $content,
        public readonly float $score,
        public readonly array $metadata,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'chunk_id' => $this->chunkId,
            'document_id' => $this->documentId,
            'title' => $this->title,
            'content' => $this->content,
            'score' => round($this->score, 4),
            'category' => $this->metadata['category'] ?? null,
            'department' => $this->metadata['department'] ?? null,
            'service_type' => $this->metadata['service_type'] ?? null,
            'version' => $this->metadata['version'] ?? null,
            'source' => $this->metadata['source'] ?? null,
        ];
    }
}
