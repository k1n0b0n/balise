<?php

declare(strict_types=1);

namespace App\Support;

/**
 * WCAG 2.2 contrast ratios between sRGB colours written as `#RRGGBB`.
 */
final class Contrast
{
    public const string BLACK = '#000000';

    public const string WHITE = '#FFFFFF';

    public static function ratio(string $first, string $second): float
    {
        [$lighter, $darker] = [max(self::luminance($first), self::luminance($second)), min(self::luminance($first), self::luminance($second))];

        return ($lighter + 0.05) / ($darker + 0.05);
    }

    /**
     * Black or white, whichever contrasts more with the background; one of them always reaches 4.5:1.
     */
    public static function readableTextOn(string $background): string
    {
        return self::ratio(self::BLACK, $background) >= self::ratio(self::WHITE, $background) ? self::BLACK : self::WHITE;
    }

    private static function luminance(string $colour): float
    {
        [$red, $green, $blue] = array_map(
            function (string $channel): float {
                $value = hexdec($channel) / 255;

                return $value <= 0.04045 ? $value / 12.92 : (($value + 0.055) / 1.055) ** 2.4;
            },
            str_split(ltrim($colour, '#'), 2),
        );

        return 0.2126 * $red + 0.7152 * $green + 0.0722 * $blue;
    }
}
