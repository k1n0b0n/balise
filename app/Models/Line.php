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
    /** For lines published without a colour, and for vehicles without a known line. */
    public const string NEUTRAL_COLOUR = '#4B5563';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    /**
     * What people call the line: its long name, "Tram A" or "Lianes 1" at TBM, else its mode and short name. Never
     * "A - Tram A": VoiceOver spells out the words after a dash in a selector's options.
     */
    public function label(): string
    {
        return $this->long_name ?? "{$this->mode->label()} {$this->short_name}";
    }

    /**
     * The published colour as `#RRGGBB`, or the neutral colour.
     */
    public function colour(): string
    {
        return $this->color === null ? self::NEUTRAL_COLOUR : '#'.strtoupper($this->color);
    }

    protected function casts(): array
    {
        return [
            'mode' => TransportMode::class,
        ];
    }
}
