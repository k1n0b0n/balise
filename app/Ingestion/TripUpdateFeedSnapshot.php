<?php

declare(strict_types=1);

namespace App\Ingestion;

use DateTimeImmutable;

final readonly class TripUpdateFeedSnapshot
{
    /**
     * @param  list<TripUpdate>  $trips
     * @param  ?DateTimeImmutable  $publishedAt  when the provider generated the feed, when the format says so
     */
    public function __construct(
        public array $trips,
        public ?DateTimeImmutable $publishedAt = null,
    ) {}
}
