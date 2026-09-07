<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserSkill extends Model
{
    protected $fillable = [
        'user_id',
        'skill',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
