<?php

declare(strict_types=1);

namespace App\Ingestion;

/**
 * The path a line's vehicles follow, as the source draws it.
 */
final readonly class CatalogShape
{
    /**
     * @param  list<string>  $lineIds  the lines whose trips follow it
     * @param  list<array{float, float}>  $points  longitude and latitude, in order
     */
    public function __construct(
        public array $lineIds,
        public array $points,
    ) {}
}
