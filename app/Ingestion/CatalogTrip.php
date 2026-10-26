<?php

declare(strict_types=1);

namespace App\Ingestion;

final readonly class CatalogTrip
{
    public function __construct(
        public string $id,
        public string $lineId,
        public ?string $headsign,
    ) {}
}
