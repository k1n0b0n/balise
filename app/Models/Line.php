<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TransportMode;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $network
 * @property string $short_name
 * @property ?string $long_name
 * @property TransportMode $mode
 * @property ?string $color
 * @property ?string $text_color
 */
final class Line extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'mode' => TransportMode::class,
        ];
    }
}
