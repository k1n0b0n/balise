<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Latest known position of a vehicle, written only by App\Actions\RecordPositions.
 *
 * @property string $vehicle_id
 * @property string $network
 * @property float $latitude
 * @property float $longitude
 * @property ?string $line_id
 * @property ?string $trip_id
 * @property ?string $destination
 * @property Carbon $recorded_at
 * @property Carbon $received_at
 * @property-read ?Line $line
 */
final class VehiclePosition extends Model
{
    public $incrementing = false;

    public $timestamps = false;

    protected $primaryKey = 'vehicle_id';

    protected $keyType = 'string';

    protected $guarded = [];

    /**
     * The line from the static catalogue; null when the feed gave none or the catalogue does not know it.
     *
     * @return BelongsTo<Line, $this>
     */
    public function line(): BelongsTo
    {
        return $this->belongsTo(Line::class);
    }

    /**
     * The vehicle id as the feed gave it, without the network prefix.
     */
    public function displayId(): string
    {
        return Str::after($this->vehicle_id, "{$this->network}:");
    }

    /**
     * What the text view and the popup call the vehicle (ADR-0018): its line and destination, "Tram A - La Gardette",
     * or its number when either is unknown.
     */
    public function name(): string
    {
        return $this->isNamed()
            ? "{$this->line?->mode->label()} {$this->line?->short_name} - {$this->destination}"
            : "Véhicule {$this->displayId()}";
    }

    /**
     * Whether the name is a line and destination, which vehicles share: the number then has to be said too (RGAA).
     */
    public function isNamed(): bool
    {
        return $this->line !== null && $this->destination !== null;
    }

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'recorded_at' => 'datetime',
            'received_at' => 'datetime',
        ];
    }
}
