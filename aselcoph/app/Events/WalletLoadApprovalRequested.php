<?php

namespace App\Events;

use App\Models\User;
use App\Models\WalletLoadRequest;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WalletLoadApprovalRequested
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public WalletLoadRequest $request,
        public User $maker,
    ) {
    }
}
