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
    ) {}
}
