<?php

declare(strict_types=1);

namespace App\Ingestion;

use App\Ingestion\Gtfs\GtfsArchive;
use App\Ingestion\Gtfs\GtfsCatalog;
use Illuminate\Http\Client\Factory as Http;
use Illuminate\Support\Facades\Config;
use InvalidArgumentException;
use RuntimeException;

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

    /**
     * A catalogue from a file rather than the network's source: a replayed day's (E5.2).
     */
    public function fromFile(string $network, string $path): CatalogSnapshot
    {
        $driver = Config::string("networks.{$network}.catalog.driver");
        $copy = tempnam(sys_get_temp_dir(), 'catalog');

        // The snapshot deletes its file once read: it reads a copy.
        if ($copy === false || ! copy($path, $copy)) {
            throw new RuntimeException("Cannot copy the catalogue {$path}.");
        }

        return match ($driver) {
            'gtfs' => new GtfsArchive($network, $copy, null),
            default => throw new InvalidArgumentException("Unknown catalogue driver [{$driver}] for network [{$network}]."),
        };
    }
}
