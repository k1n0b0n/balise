<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Enums\TransportMode;
use App\Models\Line;
use App\Models\Stop;
use App\Models\VehiclePosition;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Livewire\Component;

final class Home extends Component
{
    public function render(): View
    {
        $network = (string) array_key_first(Config::array('networks'));
        $lines = $this->lines($network);
        // E3.4: the line chosen in the selector, by its short name, which people read and share in the URL.
        $selectedLine = $lines->first(fn (Line $line): bool => $line->short_name === request()->string('ligne')->toString());
        $positions = VehiclePosition::query()->with('line')->where('network', $network)->get();

        return view('livewire.home', [
            'network' => $network,
            'networkName' => Config::string("networks.{$network}.name"),
            'timezone' => Config::string("networks.{$network}.timezone"),
            'refreshSeconds' => Config::integer('map.refresh_every_seconds'),
            'styleUrl' => Config::string('map.style'),
            'bounds' => $this->bounds($network),
            'neutralColour' => Line::NEUTRAL_COLOUR,
            'lineOptions' => $lines->groupBy(fn (Line $line): string => $line->mode->label()),
            'selectedLine' => $selectedLine,
            'groups' => $this->groups($positions, $selectedLine),
            'updatedAt' => $positions->max('received_at'),
        ]);
    }

    /**
     * The text view (E3.5): the selected line first (E3.4), then lines in natural order of their short name, each with
     * its vehicles, and the vehicles whose line is unknown last. The browser sorts the same way when it takes over.
     *
     * @param  Collection<int, VehiclePosition>  $positions
     * @return Collection<int, array{line: ?Line, vehicles: Collection<int, VehiclePosition>}>
     */
    private function groups(Collection $positions, ?Line $selectedLine): Collection
    {
        return $positions
            ->groupBy(fn (VehiclePosition $position): string => $position->line->id ?? '')
            ->map(fn (Collection $vehicles): array => [
                'line' => $vehicles->first()?->line,
                'vehicles' => $vehicles->sort(fn (VehiclePosition $a, VehiclePosition $b): int => strnatcasecmp($a->displayId(), $b->displayId()))->values(),
            ])
            ->sort(fn (array $a, array $b): int => match (true) {
                $a['line']?->is($selectedLine) === true => -1,
                $b['line']?->is($selectedLine) === true => 1,
                $a['line'] === null => 1,
                $b['line'] === null => -1,
                default => strnatcasecmp($a['line']->short_name, $b['line']->short_name),
            })
            ->values();
    }

    /**
     * The lines of the selector: by mode in the order of the enum, trams first, then in natural order of their short name.
     *
     * @return Collection<int, Line>
     */
    private function lines(string $network): Collection
    {
        $modes = array_flip(array_map(fn (TransportMode $mode): string => $mode->value, TransportMode::cases()));

        return Line::query()
            ->where('network', $network)
            ->get()
            ->sort(fn (Line $a, Line $b): int => $modes[$a->mode->value] <=> $modes[$b->mode->value] ?: strnatcasecmp($a->short_name, $b->short_name))
            ->values();
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
