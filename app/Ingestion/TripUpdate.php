<?php

declare(strict_types=1);

namespace App\Ingestion;

/**
 * Normalised update of one trip: what it is, and when it reaches each of its stops (E8.1).
 */
final readonly class TripUpdate
{
    /**
     * @param  list<StopTime>  $stopTimes
     */
    public function __construct(
        public string $tripId,
        public TripStatus $status,
        public array $stopTimes,
        public ?string $lineId = null,
        public ?string $vehicleId = null,
    ) {}
}
