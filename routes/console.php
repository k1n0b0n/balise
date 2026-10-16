<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schedule;

// Providers publish their static data overnight; TBM's lands around 04:50.
foreach (array_keys(Config::array('networks')) as $network) {
    Schedule::command('network:import', [$network])
        ->dailyAt('05:30')
        ->timezone(Config::string("networks.{$network}.timezone"))
        ->withoutOverlapping();
}
