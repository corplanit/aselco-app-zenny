<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketEscalation extends Model
{
    public const CREATED_AT = 'escalated_at';
    public const UPDATED_AT = null;

    protected $fillable = [
        'ticket_id',
        'escalated_from',
        'escalated_to',
        'reason',
        'resolved_at',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }
}
