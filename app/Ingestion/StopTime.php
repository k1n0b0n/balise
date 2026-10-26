<?php

declare(strict_types=1);

namespace App\Ingestion;

use DateTimeImmutable;

/**
 * When a trip leaves one of its stops, its delay included; no time when the feed gives none for that stop.
 */
final readonly class StopTime
{
    public function __construct(
        public string $stopId,
        public ?DateTimeImmutable $time,
        public bool $skipped = false,
    ) {}
}
