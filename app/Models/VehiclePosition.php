<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Latest known position of a vehicle, written only by App\Actions\RecordPositions.
 *
 * @property string $vehicle_id
 * @property string $network
 * @property float $latitude
 * @property float $longitude
 * @property ?string $line_id
 * @property ?string $trip_id
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
