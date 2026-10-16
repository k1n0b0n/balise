<?php

declare(strict_types=1);

namespace App\Ingestion;

final readonly class VehicleFeedSnapshot
{
    /**
     * @param  list<VehiclePosition>  $positions
     */
    public function __construct(
        public ?string $etag,
        public array $positions,
    ) {}
}
