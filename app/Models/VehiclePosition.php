<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
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
 */
final class VehiclePosition extends Model
{
    public $incrementing = false;

    public $timestamps = false;

    protected $primaryKey = 'vehicle_id';

    protected $keyType = 'string';

    protected $guarded = [];

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
