<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Line;
use Carbon\CarbonImmutable;

/**
 * The next departures of one line towards one destination at a station (E8.2).
 */
final class DepartureGroup
{
    /**
     * @param  list<CarbonImmutable>  $times
     */
    public function __construct(
        public readonly ?Line $line,
        public readonly ?string $destination,
        public array $times = [],
    ) {}

    /**
     * "dans 3 min" within the hour, the time beyond it: the wording of the station popup (E8.2).
     */
    public static function when(CarbonImmutable $time, CarbonImmutable $now, string $timezone): string
    {
        $minutes = (int) floor(($time->getTimestamp() - $now->getTimestamp()) / 60);

        return match (true) {
            $minutes < 1 => 'à l’approche',
            $minutes < 60 => "dans {$minutes} min",
            default => 'à '.$time->setTimezone($timezone)->format('H:i'),
        };
    }
}
