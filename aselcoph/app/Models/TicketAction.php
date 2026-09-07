<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketAction extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'ticket_id',
        'actor_id',
        'actor_role',
        'action_taken',
        'minutes_taken',
        'requires_payment',
        'requires_tsd_intervention',
    ];

    protected $casts = [
        'requires_payment' => 'boolean',
        'requires_tsd_intervention' => 'boolean',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
