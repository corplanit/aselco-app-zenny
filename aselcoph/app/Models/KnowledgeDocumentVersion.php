<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KnowledgeDocumentVersion extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'document_id',
        'version',
        'body',
        'checksum',
        'indexed_at',
        'created_by',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'indexed_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(KnowledgeDocument::class, 'document_id');
    }

    public function chunks(): HasMany
    {
        return $this->hasMany(KnowledgeChunk::class, 'version_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
