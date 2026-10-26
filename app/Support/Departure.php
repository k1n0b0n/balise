<?php

declare(strict_types=1);

namespace App\Support;

use App\Ingestion\TripStatus;
use Carbon\CarbonImmutable;

/**
 * One trip leaving one platform of a station, from the trip updates (E8.1).
 */
final readonly class Departure
{
    public function __construct(
        public CarbonImmutable $time,
        public string $stopId,
        public string $tripId,
        public TripStatus $status,
        public bool $skipped,
        public ?string $lineId = null,
        public ?string $vehicleId = null,
    ) {}
}
