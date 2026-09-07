<?php

namespace App\Listeners;

trait RetriesQueuedNotifications
{
    public int $tries = 5;

    public int $timeout = 60;

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [15, 30, 60, 120];
    }
}
