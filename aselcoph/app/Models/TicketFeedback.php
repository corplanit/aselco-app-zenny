<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketFeedback extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'ticket_feedback';

    protected $fillable = [
        'ticket_id',
        'verified_by',
        'method',
        'customer_confirmed',
        'notes',
        'needs_further_action',
    ];

    protected $casts = [
        'customer_confirmed' => 'boolean',
        'needs_further_action' => 'boolean',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
