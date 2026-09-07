<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KnowledgeChunk extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'document_id',
        'version_id',
        'chunk_index',
        'content',
        'tokens',
        'embedding',
        'embedding_model',
        'char_count',
        'active',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'tokens' => 'array',
            'embedding' => 'array',
            'active' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(KnowledgeDocument::class, 'document_id');
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(KnowledgeDocumentVersion::class, 'version_id');
    }
}
