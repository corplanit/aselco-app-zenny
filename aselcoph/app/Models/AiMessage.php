<?php

namespace App\Models;

use App\Models\Concerns\AppendOnly;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiMessage extends Model
{
    use AppendOnly;

    public $timestamps = false;

    protected $fillable = [
        'conversation_id',
        'role',
        'content',
        'intent',
        'suggested_action',
        'escalate',
        'meta',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'escalate' => 'boolean',
            'meta' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AiConversation::class, 'conversation_id');
    }
}
