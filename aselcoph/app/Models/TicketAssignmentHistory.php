<?php

namespace App\Models;

use App\Models\Concerns\AppendOnly;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketAssignmentHistory extends Model
{
    use AppendOnly;

    public $timestamps = false;

    protected $table = 'ticket_assignment_history';

    public const METHOD_MANUAL = 'manual';
    public const METHOD_AUTOMATIC = 'automatic';
    public const METHOD_AI = 'ai_recommended';
    public const METHOD_ROUND_ROBIN = 'round_robin';
    public const METHOD_LEAST_BUSY = 'least_busy';
    public const METHOD_ESCALATION = 'escalation';
    public const METHOD_SKILL = 'skill';

    protected $fillable = [
        'ticket_id',
        'previous_department',
        'new_department',
        'previous_assignee_id',
        'new_assignee_id',
        'method',
        'assigned_by',
        'reason',
        'ai_recommendation',
        'ai_confidence',
        'created_at',
    ];

    protected $casts = [
        'ai_confidence' => 'float',
        'created_at' => 'datetime',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function previousAssignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'previous_assignee_id');
    }

    public function newAssignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'new_assignee_id');
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}
