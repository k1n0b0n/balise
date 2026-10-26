<?php

declare(strict_types=1);

namespace App\Support;

use App\Ingestion\TripStatus;
use App\Ingestion\TripUpdateFeeds;
use App\Ingestion\TripUpdateFeedSnapshot;
use App\Models\Line;
use App\Models\Station;
use App\Models\Stop;
use App\Models\Trip;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The next departures at a station, from the trip updates (E8.1). The feed is read when someone asks, then kept for
 * a minute, indexed by platform: one key per platform, under the version of the read, so that a request reads only
 * its station's departures and never mixes two reads.
 */
final readonly class Departures
{
    public function __construct(private TripUpdateFeeds $feeds) {}

    /**
     * @return list<Departure>|null in time order from now on; null when the network has no trip updates to read
     */
    public function at(Station $station, CarbonImmutable $now): ?array
    {
        $network = $station->network;
        $version = $this->version($network);

        if ($version === null) {
            return null;
        }

        $keys = Stop::query()->where('station_id', $station->id)->get()
            ->map(fn (Stop $stop): string => "trip-updates:{$network}:{$version}:{$stop->id}")
            ->all();
        $departures = [];

        foreach (Cache::many($keys) as $rows) {
            foreach (is_array($rows) ? $rows : [] as $row) {
                $departure = is_array($row) ? $this->departure($row) : null;

                if ($departure !== null && $departure->time->getTimestamp() >= $now->getTimestamp()) {
                    $departures[] = $departure;
                }
            }
        }

        usort($departures, fn (Departure $a, Departure $b): int => $a->time <=> $b->time);

        return $departures;
    }

    /**
     * What a station's popup shows (E8.2): its departures in the coming hours, grouped by line then destination like the
     * text view (E3.7), the soonest group first, a few times per group. Cancelled trips and skipped stops are left out
     * (TBM gives them no time anyway, story 034), and so are trips that end at the station.
     *
     * @return list<DepartureGroup>|null null when the network has no trip updates to read
     */
    public function board(Station $station, CarbonImmutable $now, int $horizonMinutes = 120, int $perGroup = 3): ?array
    {
        $departures = $this->at($station, $now);

        if ($departures === null) {
            return null;
        }

        $until = $now->addMinutes($horizonMinutes);
        $departures = array_values(array_filter(
            $departures,
            // A trip that ends here arrives rather than leaves.
            fn (Departure $departure): bool => ! $departure->skipped && $departure->status !== TripStatus::Cancelled
                && $departure->stopId !== $departure->lastStopId && $departure->time <= $until,
        ));

        $headsigns = [];
        foreach (Trip::query()->whereIn('id', array_map(fn (Departure $departure): string => $departure->tripId, $departures))->get() as $trip) {
            $headsigns[$trip->id] = $trip->headsign;
        }
        // A trip the catalogue does not know (TBM's unplanned `nt_` trips) goes where its trip update ends.
        $lastStops = [];
        foreach (Stop::query()->whereIn('id', array_filter(array_map(fn (Departure $departure): ?string => $departure->lastStopId, $departures)))->get() as $stop) {
            $lastStops[$stop->id] = $stop->name;
        }
        $lines = Line::query()->whereIn('id', array_filter(array_map(fn (Departure $departure): ?string => $departure->lineId, $departures)))->get()->keyBy('id');

        $groups = [];
        foreach ($departures as $departure) {
            $destination = $headsigns[$departure->tripId] ?? $lastStops[$departure->lastStopId ?? ''] ?? null;
            // The catalogue writes some destinations in capitals on some trips: one group all the same.
            $key = ($departure->lineId ?? '').'|'.mb_strtolower($destination ?? '');
            $groups[$key] ??= new DepartureGroup($lines->get($departure->lineId ?? ''), $destination);

            if (count($groups[$key]->times) < $perGroup) {
                $groups[$key]->times[] = $departure->time;
            }
        }

        // The departures are in time order: so are the groups, by their first one.
        return array_values($groups);
    }

    /**
     * The version of the last read kept, reading the feed first when there is none; null when there is nothing to read.
     */
    private function version(string $network): ?string
    {
        $feed = $this->feeds->for($network);

        if ($feed === null) {
            return null;
        }

        $current = "trip-updates:{$network}:version";
        $version = Cache::get($current);

        if ($version === null) {
            // One download at a time: the others wait for it, then read what it kept; past 20 s, they answer no data.
            try {
                $version = Cache::lock("trip-updates:{$network}:read", 30)->block(20, function () use ($current, $network, $feed): string {
                    $kept = Cache::get($current);

                    return is_string($kept) ? $kept : $this->read($network, $feed->fetch(...));
                });
            } catch (LockTimeoutException) {
                return null;
            }
        }

        // An empty version is a failed read, not retried before it expires.
        return is_string($version) && $version !== '' ? $version : null;
    }

    /**
     * @param  callable(): TripUpdateFeedSnapshot  $fetch
     */
    private function read(string $network, callable $fetch): string
    {
        $keep = Config::integer("networks.{$network}.trip_updates.keep_seconds");
        $startedAt = microtime(true);

        try {
            $snapshot = $fetch();
        } catch (Throwable $exception) {
            Log::warning("Trip updates of {$network} could not be read.", ['exception' => $exception->getMessage()]);
            Cache::put("trip-updates:{$network}:version", '', $keep);

            return '';
        }

        $byStop = [];
        foreach ($snapshot->trips as $trip) {
            $lastStopId = $trip->stopTimes === [] ? null : $trip->stopTimes[array_key_last($trip->stopTimes)]->stopId;

            foreach ($trip->stopTimes as $stopTime) {
                if ($stopTime->time !== null) {
                    $byStop[$stopTime->stopId][] = [$stopTime->time->getTimestamp(), $stopTime->stopId, $trip->tripId, $trip->status->value, $stopTime->skipped, $trip->lineId, $trip->vehicleId, $lastStopId];
                }
            }
        }

        $version = bin2hex(random_bytes(4));
        // Kept twice as long as the version, so that a request that read the version still finds its rows.
        Cache::putMany(array_combine(array_map(fn (string $stop): string => "trip-updates:{$network}:{$version}:{$stop}", array_keys($byStop)), $byStop), $keep * 2);
        Cache::put("trip-updates:{$network}:version", $version, $keep);

        Log::info("Trip updates of {$network} read.", ['trips' => count($snapshot->trips), 'stops' => count($byStop), 'seconds' => round(microtime(true) - $startedAt, 2)]);

        return $version;
    }

    /**
     * A departure kept by `read()`, or null for anything else the cache may hold under that key.
     *
     * @param  array<mixed>  $row  [time (epoch seconds), stop id, trip id, trip status, skipped, line id, vehicle id, last stop id]
     */
    private function departure(array $row): ?Departure
    {
        if (count($row) !== 8) {
            return null;
        }

        [$time, $stop, $trip, $status, $skipped, $line, $vehicle, $lastStop] = $row;
        $tripStatus = is_string($status) ? TripStatus::tryFrom($status) : null;

        if (! is_int($time) || ! is_string($stop) || ! is_string($trip) || $tripStatus === null || ! is_bool($skipped)
            || ! (is_string($line) || $line === null) || ! (is_string($vehicle) || $vehicle === null) || ! (is_string($lastStop) || $lastStop === null)) {
            return null;
        }

        return new Departure(CarbonImmutable::createFromTimestamp($time), $stop, $trip, $tripStatus, $skipped, $line, $vehicle, $lastStop);
    }
}
