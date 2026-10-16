<?php

declare(strict_types=1);

namespace App\Ingestion\Gtfs;

use App\Ingestion\CatalogSnapshot;
use App\Ingestion\NetworkCatalog;
use Illuminate\Http\Client\Factory as Http;
use RuntimeException;
use Throwable;

final readonly class GtfsCatalog implements NetworkCatalog
{
    public function __construct(
        private string $network,
        private string $url,
        private Http $http,
    ) {}

    public function fetch(?string $etag): ?CatalogSnapshot
    {
        $path = tempnam(sys_get_temp_dir(), 'gtfs');

        if ($path === false) {
            throw new RuntimeException('Cannot create a temporary file for the GTFS archive.');
        }

        try {
            // The archive goes straight to disk: it is tens of megabytes for a large network.
            $response = $this->http
                ->connectTimeout(10)
                ->timeout(120)
                ->withHeaders($etag === null ? [] : ['If-None-Match' => $etag])
                ->sink($path)
                ->get($this->url)
                ->throw();
        } catch (Throwable $exception) {
            unlink($path);

            throw $exception;
        }

        if ($response->status() === 304) {
            unlink($path);

            return null;
        }

        return new GtfsArchive($this->network, $path, $response->header('ETag') ?: null);
    }
}
