<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WalletTransaction extends Model
{
    public const UPDATED_AT = null;

    public const TYPE_LOAD = 'load';
    public const TYPE_PAYMENT = 'payment';
    public const TYPE_REVERSAL = 'reversal';
    public const TYPE_ADJUSTMENT = 'adjustment';

    public const SOURCE_ADMIN = 'admin';
    public const SOURCE_SYSTEM = 'system';

    public const STATUS_PENDING = 'pending';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';
    public const STATUS_REVERSED = 'reversed';

    protected $fillable = [
        'wallet_id',
        'type',
        'amount',
        'balance_before',
        'balance_after',
        'reference_no',
        'idempotency_key',
        'source',
        'source_id',
        'status',
        'remarks',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'balance_before' => 'decimal:2',
        'balance_after' => 'decimal:2',
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $txn): void {
            if ((float) $txn->amount <= 0) {
                throw new \InvalidArgumentException('Transaction amount must be greater than zero.');
            }
            if ((float) $txn->balance_after < 0) {
                throw new \InvalidArgumentException('Transaction would make the wallet balance negative.');
            }
        });
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function sourceUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'source_id');
    }
}
