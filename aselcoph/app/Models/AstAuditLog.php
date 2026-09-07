<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AstAuditLog extends Model
{
    use \App\Models\Concerns\AppendOnly;
    protected $fillable = [
        'wallet_id',
        'ledger_entry_id',
        'actor_id',
        'actor_role',
        'action',
        'amount',
        'balance_before',
        'balance_after',
        'idempotency_key',
        'ip_address',
        'user_agent',
        'meta',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'balance_before' => 'decimal:2',
        'balance_after' => 'decimal:2',
        'meta' => 'array',
    ];

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(AstWallet::class, 'wallet_id');
    }

    public function ledgerEntry(): BelongsTo
    {
        return $this->belongsTo(AstLedgerEntry::class, 'ledger_entry_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
