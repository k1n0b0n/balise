<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schedule;

/*
 * The scheduler runs each command in its own process, with the output thrown away; the application logs to stderr in
 * containers, so the commands' log lines, failures included, reached no one. Their output goes to the scheduler's own
 * stderr instead, which is the container's log (story 025).
 */
$containerLog = '/proc/1/fd/2';

foreach (array_keys(Config::array('networks')) as $network) {
    // Providers publish their static data overnight; TBM's lands around 04:50. In the background: an import takes a
    // minute or two, and the scheduler runs its other commands one after another, so the polls would wait for it.
    Schedule::command('network:import', [$network])
        ->dailyAt('05:30')
        ->timezone(Config::string("networks.{$network}.timezone"))
        ->withoutOverlapping(expiresAt: 60)
        ->runInBackground()
        ->appendOutputTo($containerLog);

    // A short lock: a crashed run must not stop polling for the default 24 hours.
    $poll = Schedule::command('network:poll', [$network])->withoutOverlapping(expiresAt: 1)->appendOutputTo($containerLog);

    match (Config::integer("networks.{$network}.vehicles.poll_every_seconds")) {
        10 => $poll->everyTenSeconds(),
        15 => $poll->everyFifteenSeconds(),
        20 => $poll->everyTwentySeconds(),
        30 => $poll->everyThirtySeconds(),
        60 => $poll->everyMinute(),
        default => throw new InvalidArgumentException("networks.{$network}.vehicles.poll_every_seconds must be 10, 15, 20, 30 or 60."),
    };
}
