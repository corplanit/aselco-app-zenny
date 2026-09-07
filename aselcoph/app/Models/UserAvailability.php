<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserAvailability extends Model
{
    protected $table = 'user_availability';

    protected $fillable = [
        'user_id',
        'status',
        'shift',
        'starts_at',
        'ends_at',
        'on_leave',
        'note',
    ];

    protected $casts = [
        'on_leave' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
