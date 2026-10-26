<?php

declare(strict_types=1);

use App\Ingestion\GtfsRealtime\GtfsRealtimeTripUpdateFeed;
use App\Ingestion\TripStatus;
use App\Models\Station;
use App\Models\Stop;
use App\Support\Departure;
use App\Support\Departures;
use App\Support\Replays;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Sleep;
use Illuminate\Support\Str;
use Transit_realtime\FeedEntity;
use Transit_realtime\FeedHeader;
use Transit_realtime\FeedMessage;
use Transit_realtime\TripDescriptor;
use Transit_realtime\TripDescriptor\ScheduleRelationship as TripRelationship;
use Transit_realtime\TripUpdate;
use Transit_realtime\TripUpdate\StopTimeEvent;
use Transit_realtime\TripUpdate\StopTimeUpdate;
use Transit_realtime\TripUpdate\StopTimeUpdate\ScheduleRelationship as StopRelationship;
use Transit_realtime\TripUpdate\TripProperties;
use Transit_realtime\VehicleDescriptor;

beforeEach(function (): void {
    config(['networks.tbm.trip_updates.url' => 'https://rt.test/trip-updates']);
    $this->travelTo(CarbonImmutable::parse('2026-10-26 15:00:00', 'Europe/Paris'));

    // Quinconces: two platforms; Gare Saint-Jean: a lone platform, its own station.
    Station::query()->create(['id' => 'tbm:QUIN', 'network' => 'tbm', 'name' => 'Quinconces', 'latitude' => 44.84, 'longitude' => -0.57, 'sync_id' => Str::uuid()]);
    foreach (['tbm:QUIN_A' => 'tbm:QUIN', 'tbm:QUIN_B' => 'tbm:QUIN', 'tbm:GARE' => 'tbm:GARE'] as $stop => $station) {
        Stop::query()->create(['id' => $stop, 'network' => 'tbm', 'name' => $stop, 'latitude' => 44.84, 'longitude' => -0.57, 'sync_id' => Str::uuid(), 'station_id' => $station]);
    }
});

/**
 * A trip updates payload. Each trip: its id, line, status, vehicle, and its stops as [stop id, departure in minutes
 * from now or null, skipped].
 *
 * @param  list<array{id: string, route?: string, status?: int, vehicle?: string, copy?: string, stops: list<array{0: string, 1: ?int, 2?: bool}>}>  $trips
 */
function tripUpdates(array $trips): string
{
    $now = CarbonImmutable::now();
    $entities = array_map(function (array $trip) use ($now): FeedEntity {
        $update = (new TripUpdate)->setTrip((new TripDescriptor)->setTripId($trip['id'])->setRouteId($trip['route'] ?? '')->setScheduleRelationship($trip['status'] ?? TripRelationship::SCHEDULED));
        if (isset($trip['copy'])) {
            $update->setTripProperties((new TripProperties)->setTripId($trip['copy']));
        }
        if (isset($trip['vehicle'])) {
            $update->setVehicle((new VehicleDescriptor)->setId($trip['vehicle']));
        }
        $update->setStopTimeUpdate(array_map(function (array $stop) use ($now): StopTimeUpdate {
            $update = (new StopTimeUpdate)->setStopId($stop[0])->setScheduleRelationship(($stop[2] ?? false) ? StopRelationship::SKIPPED : StopRelationship::SCHEDULED);

            return $stop[1] === null ? $update : $update->setDeparture((new StopTimeEvent)->setTime($now->addMinutes($stop[1])->getTimestamp())->setDelay(60));
        }, $trip['stops']));

        return (new FeedEntity)->setId($trip['id'])->setTripUpdate($update);
    }, $trips);

    return (new FeedMessage)->setHeader((new FeedHeader)->setGtfsRealtimeVersion('2.0')->setTimestamp($now->getTimestamp()))->setEntity($entities)->serializeToString();
}

/**
 * @return list<string>|null each departure as "minutes trip status", in the order given
 */
function departuresAtQuinconces(): ?array
{
    $departures = app(Departures::class)->at(Station::query()->findOrFail('tbm:QUIN'), CarbonImmutable::now());

    return $departures === null ? null : array_map(
        fn (Departure $departure): string => CarbonImmutable::now()->diffInMinutes($departure->time).' '.$departure->tripId.' '.$departure->status->value.($departure->skipped ? ' skipped' : ''),
        $departures,
    );
}

it('reads trips, their status and their times at each stop, ids namespaced (E8.1)', function (): void {
    $snapshot = (new GtfsRealtimeTripUpdateFeed('tbm', 'https://rt.test/trip-updates', app(HttpFactory::class)))->decode(tripUpdates([
        ['id' => 'T1', 'route' => 'A', 'vehicle' => 'bus-1', 'stops' => [['QUIN_A', 3], ['GARE', null], ['QUIN_B', 9, true]]],
        ['id' => 'T2', 'status' => TripRelationship::CANCELED, 'stops' => []],
        ['id' => 'T3', 'status' => TripRelationship::ADDED, 'stops' => []],
        // Riders should not see a deleted trip; a duplicated one runs under the id of its properties.
        ['id' => 'T4', 'status' => TripRelationship::DELETED, 'stops' => []],
        ['id' => 'T5', 'status' => TripRelationship::DUPLICATED, 'copy' => 'T5-bis', 'stops' => []],
    ]));

    [$first, $cancelled, $added, $duplicated] = $snapshot->trips;
    expect($first->tripId)->toBe('tbm:T1')
        ->and($first->lineId)->toBe('tbm:A')
        ->and($first->vehicleId)->toBe('tbm:bus-1')
        ->and($first->status)->toBe(TripStatus::Scheduled)
        ->and(array_map(fn ($stopTime): array => [$stopTime->stopId, $stopTime->time?->getTimestamp(), $stopTime->skipped], $first->stopTimes))->toBe([
            ['tbm:QUIN_A', CarbonImmutable::now()->addMinutes(3)->getTimestamp(), false],
            ['tbm:GARE', null, false],
            ['tbm:QUIN_B', CarbonImmutable::now()->addMinutes(9)->getTimestamp(), true],
        ])
        ->and($cancelled->status)->toBe(TripStatus::Cancelled)
        ->and($added->status)->toBe(TripStatus::Added)
        ->and($snapshot->trips)->toHaveCount(4)
        ->and([$duplicated->tripId, $duplicated->status])->toBe(['tbm:T5-bis', TripStatus::Added])
        ->and($snapshot->publishedAt?->getTimestamp())->toBe(CarbonImmutable::now()->getTimestamp());
});

it('lists a station\'s departures from its platforms, from now on, in time order', function (): void {
    Http::fake(['rt.test/*' => Http::response(tripUpdates([
        ['id' => 'T1', 'stops' => [['QUIN_A', -2], ['GARE', 4]]],
        ['id' => 'T2', 'stops' => [['QUIN_B', 7, true]]],
        ['id' => 'T3', 'status' => TripRelationship::CANCELED, 'stops' => [['QUIN_A', 5]]],
        ['id' => 'T4', 'stops' => [['QUIN_A', 1]]],
    ]))]);

    expect(departuresAtQuinconces())->toBe(['1 tbm:T4 scheduled', '5 tbm:T3 cancelled', '7 tbm:T2 scheduled skipped']);
});

it('reads the feed once a minute at most, whatever the number of requests', function (): void {
    Http::fake(['rt.test/*' => fn () => Http::response(tripUpdates([['id' => 'T1', 'stops' => [['QUIN_A', 5]]]]))]);

    departuresAtQuinconces();
    $this->travel(59)->seconds();
    departuresAtQuinconces();
    Http::assertSentCount(1);

    $this->travel(2)->seconds();
    departuresAtQuinconces();
    Http::assertSentCount(2);
});

it('answers no data for a network without trip updates or replaying a day, without reading anything', function (?string $url, bool $replaying): void {
    config(['networks.tbm.trip_updates.url' => $url]);
    if ($replaying) {
        Storage::fake('replays');
        app(Replays::class)->start('tbm', '2026-10-21', '12:00', CarbonImmutable::now());
    }
    Http::fake();

    expect(departuresAtQuinconces())->toBeNull();
    Http::assertNothingSent();
})->with([
    'no trip updates' => [null, false],
    'replaying' => ['https://rt.test/trip-updates', true],
]);

it('logs a failed read, answers no data and waits a minute before trying again', function (): void {
    Log::spy();
    Http::fake(['rt.test/*' => Http::response('', 503)]);

    expect(departuresAtQuinconces())->toBeNull()
        ->and(departuresAtQuinconces())->toBeNull();

    Http::assertSentCount(1);
    Log::shouldHaveReceived('warning')->withArgs(fn (string $message): bool => $message === 'Trip updates of tbm could not be read.');
});

it('answers no data rather than wait more than 20 s for another read', function (): void {
    Sleep::fake(syncWithCarbon: true);
    Http::fake();
    Cache::lock('trip-updates:tbm:read', 30)->get();

    expect(departuresAtQuinconces())->toBeNull();
    Http::assertNothingSent();
});
