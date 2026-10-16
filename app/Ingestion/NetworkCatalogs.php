<?php

declare(strict_types=1);

namespace App\Ingestion;

use App\Ingestion\Gtfs\GtfsCatalog;
use Illuminate\Http\Client\Factory as Http;
use Illuminate\Support\Facades\Config;
use InvalidArgumentException;

/**
 * Builds the catalogue driver that a network declares in config/networks.php.
 */
final readonly class NetworkCatalogs
{
    public function __construct(private Http $http) {}

    public function for(string $network): NetworkCatalog
    {
        $driver = Config::string("networks.{$network}.catalog.driver");

        return match ($driver) {
            'gtfs' => new GtfsCatalog($network, Config::string("networks.{$network}.catalog.url"), $this->http),
            default => throw new InvalidArgumentException("Unknown catalogue driver [{$driver}] for network [{$network}]."),
        };
    }
}
