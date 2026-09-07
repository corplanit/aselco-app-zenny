<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

$ticketSlaSchedule = Schedule::command('tickets:check-sla')->withoutOverlapping();

if ((int) config('tickets.escalation.command_frequency_minutes', 5) <= 1) {
    $ticketSlaSchedule->everyMinute();
} else {
    $ticketSlaSchedule->everyFiveMinutes();
}
