<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WalletLoadFailed
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param  array<string, mixed>  $details
     */
    public function __construct(
        public ?User $actor,
        public string $reason,
        public array $details = [],
    ) {
    }
}
