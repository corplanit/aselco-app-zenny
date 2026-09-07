<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BillingUpload extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_PARTIALLY_PAID = 'partially_paid';
    public const STATUS_PAID = 'paid';

    protected $fillable = [
        'account_link_id',
        'file_path',
        'amount',
        'billing_date',
        'uploaded_by',
        'status',
        'paid_amount',
        'balance_due',
        'last_payment_reference',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'balance_due' => 'decimal:2',
        'billing_date' => 'date',
    ];

    public function accountLink(): BelongsTo
    {
        return $this->belongsTo(AccountLink::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function astPayments(): HasMany
    {
        return $this->hasMany(AstLedgerEntry::class, 'billing_upload_id');
    }

    public function isOutstanding(): bool
    {
        return (float) $this->balance_due > 0;
    }
}

