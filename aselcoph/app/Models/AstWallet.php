<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AstWallet extends Model
{
    protected $fillable = [
        'account_number',
        'user_id',
        'balance',
    ];

    protected $casts = [
        'balance' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function entries(): HasMany
    {
        return $this->hasMany(AstLedgerEntry::class, 'wallet_id');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AstAuditLog::class, 'wallet_id');
    }
}
