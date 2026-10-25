<?php

declare(strict_types=1);

namespace App\Ingestion;

use App\Ingestion\GtfsRealtime\GtfsRealtimeDecoder;
use App\Ingestion\GtfsRealtime\GtfsRealtimeFeed;
use App\Ingestion\GtfsRealtime\ReplayFeed;
use App\Support\Replays;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Factory as Http;
use Illuminate\Support\Facades\Config;
use InvalidArgumentException;

/**
 * Builds the vehicle feed driver that a network declares in config/networks.php, or its replay while one runs (E5.2).
 */
final readonly class VehicleFeeds
{
    public function __construct(
        private Http $http,
        private Replays $replays,
    ) {}

    public function for(string $network): VehicleFeed
    {
        $driver = Config::string("networks.{$network}.vehicles.driver");

        return match ($driver) {
            'gtfs-rt' => $this->gtfsRealtime($network),
            default => throw new InvalidArgumentException("Unknown vehicle feed driver [{$driver}] for network [{$network}]."),
        };
    }

    private function gtfsRealtime(string $network): VehicleFeed
    {
        $decoder = new GtfsRealtimeDecoder(
            $network,
            array_values(array_filter(Config::array("networks.{$network}.vehicles.vehicle_id", GtfsRealtimeDecoder::VEHICLE_ID_SOURCES), is_string(...))),
            Config::boolean("networks.{$network}.vehicles.label_is_destination", false),
        );
        $replay = $this->replays->current($network);

        if ($replay !== null) {
            return new ReplayFeed(
                $this->replays->directory($network, $replay['day']),
                fn (): int => (int) $this->replays->replayedAt($network, CarbonImmutable::now()),
                $decoder,
            );
        }

        return new GtfsRealtimeFeed(Config::string("networks.{$network}.vehicles.url"), $decoder, $this->http);
    }
}
