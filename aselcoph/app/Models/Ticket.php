<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ticket extends Model
{
    public const STATUS_NEW = 'new';
    public const STATUS_ENDORSED = 'endorsed';
    public const STATUS_ASSIGNED = 'assigned';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_AWAITING_FEEDBACK = 'awaiting_feedback';
    public const STATUS_ESCALATED = 'escalated';
    public const STATUS_RESOLVED = 'resolved';
    public const STATUS_CLOSED = 'closed';
    public const STATUS_REOPENED = 'reopened';

    protected $fillable = [
        'ticket_no',
        'customer_id',
        'category_id',
        'subcategory',
        'channel',
        'description',
        'status',
        'priority',
        'assigned_to',
        'assigned_department',
        'sla_due_at',
        'created_by',
    ];

    protected $casts = [
        'sla_due_at' => 'datetime',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(TicketCategory::class, 'category_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(TicketStatusHistory::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(TicketAttachment::class);
    }

    public function actions(): HasMany
    {
        return $this->hasMany(TicketAction::class);
    }

    public function feedback(): HasMany
    {
        return $this->hasMany(TicketFeedback::class);
    }

    public function escalations(): HasMany
    {
        return $this->hasMany(TicketEscalation::class);
    }

    public function aiAnalyses(): HasMany
    {
        return $this->hasMany(TicketAiAnalysis::class);
    }

    public function latestAiAnalysis(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(TicketAiAnalysis::class)->latestOfMany();
    }

    public function aiResponseDrafts(): HasMany
    {
        return $this->hasMany(TicketAiResponseDraft::class);
    }

    public function assignmentHistory(): HasMany
    {
        return $this->hasMany(TicketAssignmentHistory::class);
    }
}
