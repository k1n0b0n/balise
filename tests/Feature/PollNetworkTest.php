<?php

declare(strict_types=1);

use App\Ingestion\VehicleFeeds;
use App\Ingestion\VehiclePosition;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Transit_realtime\FeedEntity;

beforeEach(function (): void {
    config(['networks.tbm.vehicles.url' => 'https://rt.test/tbm']);
});

/**
 * @return list<VehiclePosition>
 */
function fetchPositions(string $payload): array
{
    Http::fake(['rt.test/*' => Http::response($payload, 200, ['ETag' => '"rt1"'])]);

    return app(VehicleFeeds::class)->for('tbm')->fetch(null)->positions ?? [];
}

it('reads a recorded TBM payload into normalised positions', function (): void {
    $positions = fetchPositions((string) file_get_contents(__DIR__.'/../fixtures/gtfs-rt/tbm-vehicle-positions.pb'));

    expect($positions)->toHaveCount(403);

    $bus = collect($positions)->firstWhere('vehicleId', 'tbm:ineo-bus:2378');
    expect($bus)->toBeInstanceOf(VehiclePosition::class)
        ->and(round($bus->latitude, 5))->toBe(44.87931)
        ->and(round($bus->longitude, 5))->toBe(-0.68371)
        ->and($bus->recordedAt->getTimestamp())->toBe(1792145344)
        ->and($bus->lineId)->toBe('tbm:901')
        ->and($bus->tripId)->toBe('tbm:b_268441185_16')
        ->and($bus->destination)->toBe('GARE ST-JEAN MECA');
});

it('applies the ADR-0013 fallbacks', function (): void {
    $positions = collect(fetchPositions(feedPayload([
        feedEntity('e1', ['lat' => 44.8, 'lon' => -0.5, 'id' => 'bus-1', 'route' => '01', 'trip' => 't1', 'timestamp' => 1792145300]),
        feedEntity('e2', ['lat' => 44.8, 'lon' => -0.5, 'label' => 'bus-2', 'route' => '01']),
        feedEntity('e3', ['lat' => 44.8, 'lon' => -0.5]),
        feedEntity('e4', ['id' => 'no-position', 'timestamp' => 1792145300]),
        (new FeedEntity)->setId('e5'),
    ])))->keyBy('vehicleId');

    expect($positions->keys()->all())->toBe(['tbm:bus-1', 'tbm:bus-2', 'tbm:e3']);

    expect($positions['tbm:bus-1'])
        ->recordedAt->getTimestamp()->toBe(1792145300)
        ->lineId->toBe('tbm:01')
        ->tripId->toBe('tbm:t1');

    expect($positions['tbm:bus-2'])
        ->recordedAt->getTimestamp()->toBe(1792145353)
        ->tripId->toBeNull();

    expect($positions['tbm:e3'])
        ->lineId->toBeNull()
        ->tripId->toBeNull();
});

it('takes the destination from the label only on a network whose label is one (ADR-0018)', function (bool $labelIsDestination, ?string $destination): void {
    config(['networks.tbm.vehicles.label_is_destination' => $labelIsDestination]);

    $positions = collect(fetchPositions(feedPayload([
        feedEntity('e1', ['lat' => 44.8, 'lon' => -0.5, 'id' => 'bus-1', 'label' => 'GARE ST-JEAN MECA']),
        feedEntity('e2', ['lat' => 44.8, 'lon' => -0.5, 'id' => 'bus-2']),
    ])))->keyBy('vehicleId');

    expect($positions['tbm:bus-1']->destination)->toBe($destination)
        ->and($positions['tbm:bus-2']->destination)->toBeNull();
})->with([
    'a destination sign' => [true, 'GARE ST-JEAN MECA'],
    'a fleet number' => [false, null],
]);

it('drops positions with impossible coordinates', function (): void {
    $positions = fetchPositions(feedPayload([
        feedEntity('e1', ['lat' => 0.0, 'lon' => 0.0, 'id' => 'null-island']),
        feedEntity('e2', ['lat' => 91.0, 'lon' => -0.5, 'id' => 'too-north']),
        feedEntity('e3', ['lat' => NAN, 'lon' => -0.5, 'id' => 'not-a-number']),
    ]));

    expect(array_map(fn (VehiclePosition $position): string => $position->vehicleId, $positions))->toBe(['tbm:null-island']);
});

it('drops positions that cannot be dated', function (): void {
    expect(fetchPositions(feedPayload([feedEntity('e1', ['lat' => 44.8, 'lon' => -0.5, 'id' => 'bus-1'])], headerTimestamp: 0)))
        ->toBe([]);
});

it('sends the last ETag and stops on 304', function (): void {
    Http::fakeSequence('rt.test/*')
        ->push(feedPayload([feedEntity('e1', ['lat' => 44.8, 'lon' => -0.5, 'id' => 'bus-1'])]), 200, ['ETag' => '"rt1"'])
        ->push('', 304);

    $this->artisan('network:poll', ['network' => 'tbm'])
        ->expectsOutputToContain('tbm: 1 read, 1 dropped as stale, 0 changed, 0 removed.')
        ->assertSuccessful();
    $this->artisan('network:poll', ['network' => 'tbm'])
        ->expectsOutputToContain('tbm: feed unchanged, 0 removed.')
        ->assertSuccessful();

    Http::assertSent(fn (Request $request): bool => $request->header('If-None-Match') === ['"rt1"']);
});

it('logs a failed or invalid fetch and lets the next run retry', function (): void {
    Log::spy();

    Http::fakeSequence('rt.test/*')
        ->push('Service unavailable', 503)
        ->push('not a protobuf payload', 200)
        ->push('', 200, ['ETag' => '"empty"']);

    $this->artisan('network:poll', ['network' => 'tbm'])->assertFailed();
    $this->artisan('network:poll', ['network' => 'tbm'])->assertFailed();
    $this->artisan('network:poll', ['network' => 'tbm'])->assertFailed();

    Log::shouldHaveReceived('warning')->times(3);
    expect(Cache::get('vehicle-feed:tbm:etag'))->toBeNull();
});

it('polls every network at its configured interval without overlapping', function (): void {
    $event = collect(app(Schedule::class)->events())
        ->first(fn ($event): bool => str_contains((string) $event->command, "network:poll 'tbm'"));

    expect($event)->not->toBeNull()
        ->and($event->repeatSeconds)->toBe(20)
        ->and($event->withoutOverlapping)->toBeTrue()
        ->and($event->expiresAt)->toBe(1);
});
