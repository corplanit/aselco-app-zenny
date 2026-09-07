<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class KnowledgeDocument extends Model
{
    use SoftDeletes;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';
    public const STATUS_DRAFT = 'draft';

    protected $fillable = [
        'category_id',
        'title',
        'department',
        'service_type',
        'source',
        'status',
        'current_version',
        'effective_at',
        'expires_at',
        'file_path',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'effective_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(KnowledgeCategory::class, 'category_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(KnowledgeDocumentVersion::class, 'document_id');
    }

    public function chunks(): HasMany
    {
        return $this->hasMany(KnowledgeChunk::class, 'document_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function currentVersionRecord(): ?KnowledgeDocumentVersion
    {
        return $this->versions()->where('version', $this->current_version)->first();
    }

    public function isRetrievable(): bool
    {
        if ($this->status !== self::STATUS_ACTIVE) {
            return false;
        }

        if ($this->effective_at && $this->effective_at->isFuture()) {
            return false;
        }

        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }

        return true;
    }
}
