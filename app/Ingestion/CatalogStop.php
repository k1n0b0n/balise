<?php

declare(strict_types=1);

namespace App\Ingestion;

final readonly class CatalogStop
{
    public function __construct(
        public string $id,
        public string $name,
        public float $latitude,
        public float $longitude,
        // The station it belongs to (E7.1): its own id when it belongs to none.
        public ?string $stationId = null,
    ) {}
}
