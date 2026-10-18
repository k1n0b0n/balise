<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\VehiclePosition;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A vehicle's position; what its line looks like comes once from the line catalogue (E3.5, RGESN).
 *
 * @mixin VehiclePosition
 */
final class VehicleResource extends JsonResource
{
    /**
     * @return array{id: string, latitude: float, longitude: float, recordedAt: string, lineId: ?string}
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->vehicle_id,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'recordedAt' => $this->recorded_at->toIso8601String(),
            'lineId' => $this->line_id,
        ];
    }
}
