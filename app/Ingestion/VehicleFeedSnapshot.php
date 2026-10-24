<?php

declare(strict_types=1);

namespace App\Ingestion;

use DateTimeImmutable;

final readonly class VehicleFeedSnapshot
{
    /**
     * @param  list<VehiclePosition>  $positions
     * @param  ?DateTimeImmutable  $publishedAt  when the provider generated the feed, when the format says so
     */
    public function __construct(
        public ?string $etag,
        public array $positions,
        public ?DateTimeImmutable $publishedAt = null,
    ) {}
}
