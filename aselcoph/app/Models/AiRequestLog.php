<?php

namespace App\Models;

use App\Models\Concerns\AppendOnly;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiRequestLog extends Model
{
    use AppendOnly;

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'channel',
        'provider',
        'model',
        'prompt_chars',
        'prompt_tokens',
        'completion_tokens',
        'latency_ms',
        'ok',
        'error_code',
        'ip_address',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'ok' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
