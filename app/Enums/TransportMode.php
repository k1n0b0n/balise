<?php

declare(strict_types=1);

namespace App\Enums;

enum TransportMode: string
{
    case Tram = 'tram';
    case Metro = 'metro';
    case Rail = 'rail';
    case Bus = 'bus';
    case Ferry = 'ferry';
    case CableCar = 'cable_car';
    case Other = 'other';
}
