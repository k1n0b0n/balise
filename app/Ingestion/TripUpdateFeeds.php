<?php

declare(strict_types=1);

namespace App\Ingestion;

use App\Ingestion\GtfsRealtime\GtfsRealtimeTripUpdateFeed;
use App\Support\Replays;
use Illuminate\Http\Client\Factory as Http;
use Illuminate\Support\Facades\Config;
use InvalidArgumentException;

/**
 * Builds the trip updates driver that a network declares in config/networks.php (E8.1).
 */
final readonly class TripUpdateFeeds
{
    public function __construct(
        private Http $http,
        private Replays $replays,
    ) {}

    /**
     * Null for a network without trip updates, or replaying a day: its recordings keep no trip updates (ADR-0022).
     */
    public function for(string $network): ?TripUpdateFeed
    {
        $url = Config::get("networks.{$network}.trip_updates.url");

        if (! is_string($url) || $url === '' || $this->replays->current($network) !== null) {
            return null;
        }

        $driver = Config::string("networks.{$network}.trip_updates.driver");

        return match ($driver) {
            'gtfs-rt' => new GtfsRealtimeTripUpdateFeed($network, $url, $this->http),
            default => throw new InvalidArgumentException("Unknown trip updates driver [{$driver}] for network [{$network}]."),
        };
    }
}
