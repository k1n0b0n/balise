<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Station;
use App\Support\DepartureGroup;
use App\Support\Departures;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Http\JsonResponse;

final class DepartureController extends Controller
{
    /**
     * A station's next departures (E8.2), read by its popup when it opens and every minute while it stays open. Times
     * are absolute, so that the page words them ("dans 3 min") at the time it shows them. `available` is false when
     * the network has no trip updates to read (night, failure, replay).
     */
    public function __invoke(Departures $departures, string $network, string $station): JsonResponse
    {
        $found = Station::query()->where('network', $network)->findOrFail($station);
        $groups = $departures->board($found, CarbonImmutable::now());

        return (new JsonResponse([
            'available' => $groups !== null,
            'groups' => array_map(fn (DepartureGroup $group): array => [
                'line' => $group->line === null ? null : ['label' => $group->line->label(), 'colour' => $group->line->colour()],
                'destination' => $group->destination,
                'times' => array_map(fn (CarbonInterface $time): int => $time->getTimestamp(), $group->times),
            ], $groups ?? []),
            // The trip updates are kept a minute: a shorter wait would read the same answer.
        ]))->setCache(['public' => true, 'max_age' => 30]);
    }
}
