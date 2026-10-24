<?php

declare(strict_types=1);

use App\Actions\RecordPositions;
use App\Events\PositionsUpdated;
use App\Ingestion\VehiclePosition;
use App\Models\VehiclePosition as StoredPosition;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    Event::fake([PositionsUpdated::class]);
    $this->now = CarbonImmutable::parse('2026-10-16 12:00:00');
});

function position(string $vehicle, int $secondsAgo, float $latitude = 44.84, ?string $line = 'tbm:A', CarbonImmutable $now = new CarbonImmutable('2026-10-16 12:00:00')): VehiclePosition
{
    return new VehiclePosition("tbm:{$vehicle}", $latitude, -0.57, $now->subSeconds($secondsAgo)->toDateTimeImmutable(), $line, null);
}

/**
 * @param  list<VehiclePosition>  $positions
 * @return array{dropped: int, changed: int, removed: int}
 */
function record(array $positions, CarbonImmutable $receivedAt): array
{
    return app(RecordPositions::class)->handle('tbm', $positions, $receivedAt);
}

it('keeps the latest fresh position per vehicle and drops stale ones', function (): void {
    $counts = record([
        position('fresh', 10),
        position('duplicate', 60, latitude: 44.80),
        position('duplicate', 30, latitude: 44.81),
        position('too-old', 301),
        position('too-early', -121),
        position('no-line', 5, line: null),
    ], $this->now);

    expect($counts)->toBe(['dropped' => 2, 'changed' => 3, 'removed' => 0])
        ->and(StoredPosition::query()->orderBy('vehicle_id')->pluck('vehicle_id')->all())->toBe(['tbm:duplicate', 'tbm:fresh', 'tbm:no-line'])
        ->and(StoredPosition::query()->findOrFail('tbm:duplicate')->latitude)->toBe(44.81)
        ->and(StoredPosition::query()->findOrFail('tbm:no-line')->line_id)->toBeNull();
});

it('reads the freshness limits from the network settings', function (): void {
    config(['networks.tbm.vehicles.max_age_seconds' => 60]);

    expect(record([position('bus', 90)], $this->now)['dropped'])->toBe(1);
});

it('announces only the vehicles that changed', function (): void {
    record([position('still', 20), position('moving', 20)], $this->now);

    Event::assertDispatched(PositionsUpdated::class, fn (PositionsUpdated $event): bool => count($event->positions) === 2);

    $later = $this->now->addSeconds(20);
    $counts = record([position('still', 20), position('moving', 0, latitude: 44.85, now: $later)], $later);

    expect($counts['changed'])->toBe(1);
    Event::assertDispatched(PositionsUpdated::class, fn (PositionsUpdated $event): bool => count($event->positions) === 1
        && $event->positions[0]->vehicle_id === 'tbm:moving'
        && $event->removedVehicleIds === []);
});

it('keeps a newer stored position when a late report arrives', function (): void {
    record([position('bus', 10, latitude: 44.85)], $this->now);
    Event::fake([PositionsUpdated::class]);

    expect(record([position('bus', 40, latitude: 44.80)], $this->now)['changed'])->toBe(0)
        ->and(StoredPosition::query()->findOrFail('tbm:bus')->latitude)->toBe(44.85);
    Event::assertNotDispatched(PositionsUpdated::class);
});

it('emits nothing when nothing changed', function (): void {
    record([position('bus', 20)], $this->now);
    Event::fake([PositionsUpdated::class]);

    expect(record([position('bus', 20)], $this->now->addSeconds(20))['changed'])->toBe(0);
    Event::assertNotDispatched(PositionsUpdated::class);
});

it('removes vehicles whose last position became too old', function (): void {
    record([position('leaving', 200), position('staying', 0)], $this->now);

    $later = $this->now->addSeconds(120);
    $counts = record([position('staying', 0, now: $later)], $later);

    expect($counts['removed'])->toBe(1)
        ->and(StoredPosition::query()->pluck('vehicle_id')->all())->toBe(['tbm:staying']);
    Event::assertDispatched(PositionsUpdated::class, fn (PositionsUpdated $event): bool => $event->removedVehicleIds === ['tbm:leaving']);
});

it('records what each poll reads', function (): void {
    config(['networks.tbm.vehicles.url' => 'https://rt.test/tbm']);
    $this->travelTo($this->now);

    Http::fake(['rt.test/*' => Http::response(feedPayload([
        feedEntity('e1', ['lat' => 44.8, 'lon' => -0.5, 'id' => 'bus-1', 'route' => '01', 'timestamp' => $this->now->subSeconds(10)->getTimestamp()]),
        feedEntity('e2', ['lat' => 44.8, 'lon' => -0.5, 'id' => 'bus-2', 'timestamp' => $this->now->subHours(2)->getTimestamp()]),
    ], $this->now->getTimestamp()), 200)]);

    $this->artisan('network:poll', ['network' => 'tbm'])
        ->expectsOutputToContain('tbm: 2 read, 1 dropped as stale, 1 changed, 0 removed.')
        ->assertSuccessful();

    expect(StoredPosition::query()->findOrFail('tbm:bus-1')->line_id)->toBe('tbm:01');
});

it('counts a new destination as a change', function (): void {
    $at = $this->now->toDateTimeImmutable();
    record([new VehiclePosition('tbm:bus', 44.84, -0.57, $at, 'tbm:01', destination: 'ARTIGUES BLANCHERIE')], $this->now);

    expect(record([new VehiclePosition('tbm:bus', 44.84, -0.57, $at, 'tbm:01', destination: 'GARE ST-JEAN MECA')], $this->now)['changed'])->toBe(1)
        ->and(StoredPosition::query()->findOrFail('tbm:bus')->destination)->toBe('GARE ST-JEAN MECA');
});

it('reads the stored state once and writes once, whatever the number of vehicles', function (int $vehicles): void {
    $fleet = fn (float $latitude, CarbonImmutable $now): array => array_map(
        fn (int $number): VehiclePosition => position("bus-{$number}", 0, latitude: $latitude, now: $now),
        range(1, $vehicles),
    );
    record($fleet(44.84, $this->now), $this->now);
    $later = $this->now->addSeconds(20);

    DB::enableQueryLog();
    record($fleet(44.85, $later), $later);

    expect(array_map(fn (array $query): string => strtok($query['query'], ' '), DB::getQueryLog()))->toBe(['select', 'insert']);
    Event::assertDispatched(PositionsUpdated::class, fn (PositionsUpdated $event): bool => count($event->positions) === $vehicles
        && $event->positions[0]->latitude === 44.85
        && $event->positions[0]->recorded_at->equalTo($later));
})->with([1, 50]);

it('removes a vehicle whose last position is older than the limit by less than a second', function (): void {
    record([position('leaving', 0)], $this->now);

    // Received 300.4 s after the stored position, which the database keeps to the second.
    $later = $this->now->addSeconds(300)->addMilliseconds(400);

    expect(record([], $later)['removed'])->toBe(1);
});
