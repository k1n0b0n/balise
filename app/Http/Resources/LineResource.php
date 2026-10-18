<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Line;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A line as the map and the text view show it.
 *
 * @mixin Line
 */
final class LineResource extends JsonResource
{
    /**
     * @return array{id: string, shortName: string, longName: ?string, mode: string, modeName: string, colour: string}
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'shortName' => $this->short_name,
            'longName' => $this->long_name,
            'mode' => $this->mode->value,
            'modeName' => $this->mode->label(),
            'colour' => $this->colour(),
        ];
    }
}
