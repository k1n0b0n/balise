<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schedule;

foreach (array_keys(Config::array('networks')) as $network) {
    // Providers publish their static data overnight; TBM's lands around 04:50.
    Schedule::command('network:import', [$network])
        ->dailyAt('05:30')
        ->timezone(Config::string("networks.{$network}.timezone"))
        ->withoutOverlapping();

    $poll = Schedule::command('network:poll', [$network])->withoutOverlapping();

    match (Config::integer("networks.{$network}.vehicles.poll_every_seconds")) {
        10 => $poll->everyTenSeconds(),
        15 => $poll->everyFifteenSeconds(),
        20 => $poll->everyTwentySeconds(),
        30 => $poll->everyThirtySeconds(),
        60 => $poll->everyMinute(),
        default => throw new InvalidArgumentException("networks.{$network}.vehicles.poll_every_seconds must be 10, 15, 20, 30 or 60."),
    };
}
