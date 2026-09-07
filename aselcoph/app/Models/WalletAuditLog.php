<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WalletAuditLog extends Model
{
    use \App\Models\Concerns\AppendOnly;
    public const UPDATED_AT = null;

    public const ACTOR_ADMIN = 'admin';
    public const ACTOR_CUSTOMER = 'customer';
    public const ACTOR_SYSTEM = 'system';

    protected $fillable = [
        'wallet_id',
        'actor_id',
        'actor_type',
        'action',
        'old_value',
        'new_value',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'old_value' => 'array',
        'new_value' => 'array',
        'created_at' => 'datetime',
    ];

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
