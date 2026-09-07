<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketAiResponseDraft extends Model
{
    public const STATUS_PENDING = 'pending_review';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_SENT = 'sent';

    protected $fillable = [
        'ticket_id',
        'analysis_id',
        'draft',
        'edited_body',
        'status',
        'confidence',
        'auto_send_eligible',
        'auto_sent',
        'created_by_ai',
        'reviewed_by',
        'reviewed_at',
        'review_notes',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'confidence' => 'float',
            'auto_send_eligible' => 'boolean',
            'auto_sent' => 'boolean',
            'created_by_ai' => 'boolean',
            'reviewed_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function analysis(): BelongsTo
    {
        return $this->belongsTo(TicketAiAnalysis::class, 'analysis_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function bodyForSend(): string
    {
        return (string) ($this->edited_body ?: $this->draft);
    }
}
