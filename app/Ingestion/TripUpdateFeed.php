<?php

declare(strict_types=1);

namespace App\Ingestion;

/**
 * Port for a network's live trip updates: the times of its trips at their stops (ADR-0001, E8.1).
 */
interface TripUpdateFeed
{
    public function fetch(): TripUpdateFeedSnapshot;
}
