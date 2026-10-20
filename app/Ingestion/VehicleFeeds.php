<?php

declare(strict_types=1);

namespace App\Ingestion;

use App\Ingestion\GtfsRealtime\GtfsRealtimeFeed;
use Illuminate\Http\Client\Factory as Http;
use Illuminate\Support\Facades\Config;
use InvalidArgumentException;

/**
 * Builds the vehicle feed driver that a network declares in config/networks.php.
 */
final readonly class VehicleFeeds
{
    public function __construct(private Http $http) {}

    public function for(string $network): VehicleFeed
    {
        $driver = Config::string("networks.{$network}.vehicles.driver");

        return match ($driver) {
            'gtfs-rt' => new GtfsRealtimeFeed(
                $network,
                Config::string("networks.{$network}.vehicles.url"),
                array_values(array_filter(Config::array("networks.{$network}.vehicles.vehicle_id", GtfsRealtimeFeed::VEHICLE_ID_SOURCES), is_string(...))),
                Config::boolean("networks.{$network}.vehicles.label_is_destination", false),
                $this->http,
            ),
            default => throw new InvalidArgumentException("Unknown vehicle feed driver [{$driver}] for network [{$network}]."),
        };
    }
}
