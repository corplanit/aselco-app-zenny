<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AstLedgerEntry extends Model
{
    public const TYPE_LOAD = 'load';
    public const TYPE_PAY = 'pay';
    public const TYPE_ADJUST = 'adjust';
    public const TYPE_REVERSAL = 'reversal';

    public const CIS_NOT_APPLICABLE = 'not_applicable';
    public const CIS_PENDING_POST = 'pending_post';
    public const CIS_POSTED = 'posted';
    public const CIS_FAILED = 'failed';

    protected $fillable = [
        'wallet_id',
        'billing_upload_id',
        'type',
        'amount',
        'balance_after',
        'idempotency_key',
        'reference',
        'created_by',
        'cis_status',
        'cis_posted_at',
        'cis_posted_by',
        'cis_external_ref',
        'meta',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'balance_after' => 'decimal:2',
        'cis_posted_at' => 'datetime',
        'meta' => 'array',
    ];

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(AstWallet::class, 'wallet_id');
    }

    public function billingUpload(): BelongsTo
    {
        return $this->belongsTo(BillingUpload::class, 'billing_upload_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function cisPoster(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cis_posted_by');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AstAuditLog::class, 'ledger_entry_id');
    }
}
