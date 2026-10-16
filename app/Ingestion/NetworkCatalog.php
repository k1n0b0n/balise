<?php

declare(strict_types=1);

namespace App\Ingestion;

/**
 * Port for a network's static data: lines and stops (ADR-0001).
 */
interface NetworkCatalog
{
    /**
     * Download the catalogue, or return null when the source has not changed since $etag.
     */
    public function fetch(?string $etag): ?CatalogSnapshot;
}
