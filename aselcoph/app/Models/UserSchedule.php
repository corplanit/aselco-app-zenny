<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserSchedule extends Model
{
    protected $fillable = [
        'user_id',
        'weekday',
        'starts_at',
        'ends_at',
        'shift',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
