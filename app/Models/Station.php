<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A group of platforms under one name (E7.1); a platform that belongs to none stands for itself.
 *
 * @property string $id
 * @property string $network
 * @property string $name
 * @property float $latitude
 * @property float $longitude
 */
final class Station extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
        ];
    }
}
