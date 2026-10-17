<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Line;
use App\Models\VehiclePosition;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

/**
 * A vehicle as drawn on the map: its position and the colours of its line (ADR-0013).
 *
 * @mixin VehiclePosition
 */
final class VehicleResource extends JsonResource
{
    /**
     * @return array{id: string, displayId: string, latitude: float, longitude: float, recordedAt: string, line: ?string, lineName: ?string, mode: ?string, colour: string}
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->vehicle_id,
            'displayId' => Str::after($this->vehicle_id, "{$this->network}:"),
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'recordedAt' => $this->recorded_at->toIso8601String(),
            'line' => $this->line?->short_name,
            'lineName' => $this->line?->long_name,
            'mode' => $this->line?->mode->value,
            'colour' => $this->line?->colour() ?? Line::NEUTRAL_COLOUR,
        ];
    }
}
