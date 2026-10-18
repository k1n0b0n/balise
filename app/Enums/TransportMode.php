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

    /**
     * The mode as written in the interface, which is in French.
     */
    public function label(): string
    {
        return match ($this) {
            self::Tram => 'Tram',
            self::Metro => 'Métro',
            self::Rail => 'Train',
            self::Bus => 'Bus',
            self::Ferry => 'Bateau',
            self::CableCar => 'Téléphérique',
            self::Other => 'Autre',
        };
    }
}
