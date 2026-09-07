<?php

namespace App\Models;

use App\Models\Concerns\AppendOnly;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KnowledgeRetrievalLog extends Model
{
    use AppendOnly;

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'channel',
        'query_hash',
        'query_chars',
        'hit_count',
        'top_score',
        'sufficient',
        'hit_ids',
        'ip_address',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'sufficient' => 'boolean',
            'hit_ids' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
