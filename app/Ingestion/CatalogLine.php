<?php

declare(strict_types=1);

namespace App\Ingestion;

use App\Enums\TransportMode;

final readonly class CatalogLine
{
    public function __construct(
        public string $id,
        public string $shortName,
        public ?string $longName,
        public TransportMode $mode,
        public ?string $color,
        public ?string $textColor,
    ) {}
}
