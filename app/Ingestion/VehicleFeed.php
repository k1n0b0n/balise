<?php

declare(strict_types=1);

namespace App\Ingestion;

/**
 * Port for a network's live vehicle positions (ADR-0001).
 */
interface VehicleFeed
{
    /**
     * Fetch the current positions, or return null when the feed has not changed since $etag.
     */
    public function fetch(?string $etag): ?VehicleFeedSnapshot;
}
