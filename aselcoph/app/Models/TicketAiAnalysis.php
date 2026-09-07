<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TicketAiAnalysis extends Model
{
    public const SENTIMENTS = [
        'positive',
        'neutral',
        'negative',
        'highly_negative',
        'urgent_distressed',
    ];

    public const PRIORITIES = ['low', 'normal', 'high', 'urgent'];

    protected $fillable = [
        'ticket_id',
        'recommended_category_id',
        'category_matches_submitted',
        'recommended_priority',
        'sentiment',
        'confidence',
        'summary',
        'suggested_department',
        'suggested_assignee_id',
        'recommended_next_action',
        'suggested_response',
        'routing_validation',
        'knowledge_citations',
        'raw_payload',
        'model',
        'source',
        'ok',
        'error_code',
        'human_override',
        'override_field',
        'override_reason',
        'override_by',
        'override_at',
        'analyzed_at',
    ];

    protected function casts(): array
    {
        return [
            'category_matches_submitted' => 'boolean',
            'confidence' => 'float',
            'routing_validation' => 'array',
            'knowledge_citations' => 'array',
            'raw_payload' => 'array',
            'ok' => 'boolean',
            'human_override' => 'boolean',
            'override_at' => 'datetime',
            'analyzed_at' => 'datetime',
        ];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function recommendedCategory(): BelongsTo
    {
        return $this->belongsTo(TicketCategory::class, 'recommended_category_id');
    }

    public function suggestedAssignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'suggested_assignee_id');
    }

    public function overrideBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'override_by');
    }

    public function responseDrafts(): HasMany
    {
        return $this->hasMany(TicketAiResponseDraft::class, 'analysis_id');
    }
}
