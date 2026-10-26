<?php

declare(strict_types=1);

namespace App\Ingestion\GtfsRealtime;

use App\Ingestion\VehicleFeed;
use App\Ingestion\VehicleFeedSnapshot;
use Illuminate\Http\Client\Factory as Http;

final readonly class GtfsRealtimeFeed implements VehicleFeed
{
    public function __construct(
        private string $url,
        private GtfsRealtimeDecoder $decoder,
        private Http $http,
    ) {}

    public function fetch(?string $etag): ?VehicleFeedSnapshot
    {
        $response = $this->http
            ->connectTimeout(5)
            ->timeout(10)
            ->withHeaders($etag === null ? [] : ['If-None-Match' => $etag])
            ->get($this->url)
            ->throw();

        if ($response->status() === 304) {
            return null;
        }

        return $this->decoder->decode($response->body(), $response->header('ETag') ?: null);
    }
}
