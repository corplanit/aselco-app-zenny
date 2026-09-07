<?php

namespace App\Events;

use App\Models\AstLedgerEntry;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WalletPaymentMade
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public User $customer,
        public AstLedgerEntry $entry,
        public array $context = [],
    ) {
    }
}
