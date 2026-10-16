<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Last successful catalogue import of a network.
 *
 * @property string $network
 * @property ?string $etag
 * @property Carbon $imported_at
 */
final class CatalogImport extends Model
{
    public $incrementing = false;

    public $timestamps = false;

    protected $primaryKey = 'network';

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'imported_at' => 'datetime',
        ];
    }
}
