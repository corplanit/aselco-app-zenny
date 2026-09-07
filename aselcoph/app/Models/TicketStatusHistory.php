<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketStatusHistory extends Model
{
    use \App\Models\Concerns\AppendOnly;

    public const UPDATED_AT = null;

    protected $table = 'ticket_status_history';

    protected $fillable = [
        'ticket_id',
        'from_status',
        'to_status',
        'changed_by',
        'remarks',
        'ip_address',
        'user_agent',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
