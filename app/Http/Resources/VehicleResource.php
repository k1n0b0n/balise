<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Line;
use App\Models\VehiclePosition;
use App\Support\Contrast;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A vehicle as drawn on the map: its position and the colours of its line (ADR-0013).
 *
 * @mixin VehiclePosition
 */
final class VehicleResource extends JsonResource
{
    /**
     * The published text colour of a line is ignored: many TBM pairs miss 4.5:1, so the label is black or white.
     *
     * @return array{id: string, latitude: float, longitude: float, line: ?string, colour: string, textColour: string}
     */
    public function toArray(Request $request): array
    {
        $colour = $this->line?->colour() ?? Line::NEUTRAL_COLOUR;

        return [
            'id' => $this->vehicle_id,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'line' => $this->line?->short_name,
            'colour' => $colour,
            'textColour' => Contrast::readableTextOn($colour),
        ];
    }
}
