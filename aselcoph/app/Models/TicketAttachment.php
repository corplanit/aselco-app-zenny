<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketAttachment extends Model
{
    public const CREATED_AT = 'uploaded_at';
    public const UPDATED_AT = null;

    protected $fillable = [
        'ticket_id',
        'uploaded_by',
        'file_path',
        'file_type',
        'file_size',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
