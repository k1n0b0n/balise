<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A trip of the static catalogue: its line and the destination its vehicle displays (E8.2).
 *
 * @property string $id
 * @property string $network
 * @property string $line_id
 * @property ?string $headsign
 */
final class Trip extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];
}
