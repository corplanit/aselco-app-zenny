<?php

namespace App\Events;

use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WalletLoaded
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public User $customer,
        public WalletTransaction $transaction,
    ) {
    }
}
