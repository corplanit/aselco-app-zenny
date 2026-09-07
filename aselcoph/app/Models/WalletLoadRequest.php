<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WalletLoadRequest extends Model
{
    public const UPDATED_AT = null;

    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_COMPLETED = 'completed';

    public const SOURCE_SUPPORT = 'support';
    public const SOURCE_THRESHOLD = 'threshold';

    protected $fillable = [
        'customer_id',
        'account_number',
        'admin_id',
        'amount',
        'reference_no',
        'idempotency_key',
        'status',
        'source',
        'remarks',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'approved_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $request): void {
            if ((float) $request->amount <= 0) {
                throw new \InvalidArgumentException('Load amount must be greater than zero.');
            }
        });
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function maker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    public function checker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function requiresSecondApprover(): bool
    {
        return (float) $this->amount >= (float) config('ast.load_approval_threshold');
    }
}
