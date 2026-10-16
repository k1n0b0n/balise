<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\VehiclePosition;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Emitted by the single write path when a network's current state changes (ADR-0002).
 */
final readonly class PositionsUpdated
{
    use Dispatchable;

    /**
     * @param  list<VehiclePosition>  $positions  new or moved vehicles only
     * @param  list<string>  $removedVehicleIds  vehicles whose last position became too old
     */
    public function __construct(
        public string $network,
        public array $positions,
        public array $removedVehicleIds,
    ) {}
}
