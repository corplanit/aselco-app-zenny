<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TAccountRaw extends Model
{
    protected $table = 't_accounts_raw';

    protected $primaryKey = 'account_no';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'account_no',
        'customer',
        'user_id',
        'status',
        'isDeleted',
        'meter_no',
        'address',
        'rate_class',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
