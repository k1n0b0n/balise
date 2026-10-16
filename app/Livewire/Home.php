<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Models\Stop;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Config;
use Livewire\Component;

final class Home extends Component
{
    public function render(): View
    {
        $network = (string) array_key_first(Config::array('networks'));

        return view('livewire.home', [
            'networkName' => Config::string("networks.{$network}.name"),
            'styleUrl' => Config::string('map.style'),
            'bounds' => $this->bounds($network),
        ]);
    }

    /**
     * The area covered by the network's stops, or null before the first catalogue import.
     *
     * @return array{float, float, float, float}|null west, south, east, north
     */
    private function bounds(string $network): ?array
    {
        $stops = Stop::query()->where('network', $network);
        [$west, $south, $east, $north] = [$stops->min('longitude'), $stops->min('latitude'), $stops->max('longitude'), $stops->max('latitude')];

        if (! is_numeric($west) || ! is_numeric($south) || ! is_numeric($east) || ! is_numeric($north)) {
            return null;
        }

        return [(float) $west, (float) $south, (float) $east, (float) $north];
    }
}
