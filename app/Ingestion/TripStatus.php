<?php

declare(strict_types=1);

namespace App\Ingestion;

enum TripStatus: string
{
    case Scheduled = 'scheduled';
    case Added = 'added';
    case Cancelled = 'cancelled';
}
