<?php

declare(strict_types=1);

namespace App\Ingestion;

use DateTimeImmutable;

/**
 * Normalised position of one vehicle (ADR-0013).
 */
final readonly class VehiclePosition
{
    public function __construct(
        public string $vehicleId,
        public float $latitude,
        public float $longitude,
        public DateTimeImmutable $recordedAt,
        public ?string $lineId = null,
        public ?string $tripId = null,
    ) {}
}
