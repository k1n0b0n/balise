<?php

declare(strict_types=1);

use App\Ingestion\VehicleFeeds;
use App\Ingestion\VehiclePosition;
use App\Models\VehiclePosition as StoredPosition;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;
use Transit_realtime\FeedEntity;

beforeEach(function (): void {
    config(['networks.tbm.vehicles.url' => 'https://rt.test/tbm']);
    Sleep::fake();
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

it('drops positions with impossible coordinates, and (0, 0), which a lost GPS sends', function (): void {
    $positions = fetchPositions(feedPayload([
        feedEntity('e1', ['lat' => 0.0, 'lon' => 0.0, 'id' => 'null-island']),
        feedEntity('e2', ['lat' => 91.0, 'lon' => -0.5, 'id' => 'too-north']),
        feedEntity('e3', ['lat' => NAN, 'lon' => -0.5, 'id' => 'not-a-number']),
        feedEntity('e4', ['lat' => 0.0, 'lon' => -0.5, 'id' => 'on-the-equator']),
    ]));

    expect(array_map(fn (VehiclePosition $position): string => $position->vehicleId, $positions))->toBe(['tbm:on-the-equator']);
});

it('drops positions that cannot be dated', function (): void {
    expect(fetchPositions(feedPayload([feedEntity('e1', ['lat' => 44.8, 'lon' => -0.5, 'id' => 'bus-1'])], headerTimestamp: 0)))
        ->toBe([]);
});

it('sends the last ETag and stops on 304', function (): void {
    Http::fakeSequence('rt.test/*')
        ->push(feedPayload([feedEntity('e1', ['lat' => 44.8, 'lon' => -0.5, 'id' => 'bus-1'])]), 200, ['ETag' => '"rt1"'])
        ->push('', 304)
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

it('still ages vehicles out while the feed is down (E2.3)', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-10-16 12:00:00'));
    StoredPosition::query()->create([
        'vehicle_id' => 'tbm:old', 'network' => 'tbm', 'latitude' => 44.84, 'longitude' => -0.57,
        'recorded_at' => now()->subMinutes(6), 'received_at' => now()->subMinutes(6),
    ]);
    Http::fake(['rt.test/*' => Http::response('Service unavailable', 503)]);

    $this->artisan('network:poll', ['network' => 'tbm'])->assertFailed();

    expect(StoredPosition::query()->count())->toBe(0);
});

it('polls every network at its configured interval without overlapping', function (): void {
    $event = collect(app(Schedule::class)->events())
        ->first(fn ($event): bool => str_contains((string) $event->command, "network:poll 'tbm'"));

    expect($event)->not->toBeNull()
        ->and($event->repeatSeconds)->toBe(20)
        ->and($event->withoutOverlapping)->toBeTrue()
        ->and($event->expiresAt)->toBe(1);
});

it('waits until just after the feed is published, learnt from the last feed\'s own time (E2.5)', function (string $now, ?string $publishedAt, ?int $waitMilliseconds): void {
    Http::fake(['rt.test/*' => Http::response(feedPayload([]), 200, ['ETag' => '"rt1"'])]);
    $this->travelTo(CarbonImmutable::parse($now));
    if ($publishedAt !== null) {
        Cache::put('vehicle-feed:tbm:published-at', CarbonImmutable::parse($publishedAt)->getTimestamp());
    }

    $this->artisan('network:poll', ['network' => 'tbm'])->assertSuccessful();

    // Built here, not in the dataset: a Sleep made before Sleep::fake() would really sleep when discarded.
    Sleep::assertSequence($waitMilliseconds === null ? [] : [Sleep::for($waitMilliseconds)->milliseconds()]);
})->with([
    // TBM publishes at :02, :22 and :42 and serves a second later; the scheduler runs at :00.
    'before the publication' => ['2026-10-16 12:00:00', '2026-10-16 11:59:42', 3000],
    'just after it' => ['2026-10-16 12:00:04', '2026-10-16 11:59:42', null],
    'half an interval after it' => ['2026-10-16 11:59:53', '2026-10-16 11:59:42', null],
    'published but not served yet' => ['2026-10-16 12:00:02.5', '2026-10-16 12:00:02', 500],
    'a provider that moved' => ['2026-10-16 12:00:00', '2026-10-16 11:59:47', 8000],
    'no feed read yet' => ['2026-10-16 12:00:00', null, null],
]);

it('remembers when the feed was published, and logs how late it was read', function (): void {
    Log::spy();
    $this->travelTo(CarbonImmutable::parse('2026-10-16 12:00:03'));
    $published = CarbonImmutable::parse('2026-10-16 12:00:02')->getTimestamp();
    Http::fake(['rt.test/*' => Http::response(feedPayload([], headerTimestamp: $published), 200, ['ETag' => '"rt1"'])]);

    $this->artisan('network:poll', ['network' => 'tbm'])->assertSuccessful();

    expect(Cache::get('vehicle-feed:tbm:published-at'))->toBe($published);
    Log::shouldHaveReceived('info')->withArgs(fn (string $message, array $context): bool => $context['seconds_after_publication'] === 1.0);
});

it('asks an unchanged feed once more, a little later', function (): void {
    Cache::put('vehicle-feed:tbm:etag', '"rt1"');
    Http::fakeSequence('rt.test/*')
        ->push('', 304)
        ->push(feedPayload([feedEntity('e1', ['lat' => 44.8, 'lon' => -0.5, 'id' => 'bus-1'])]), 200, ['ETag' => '"rt2"']);

    $this->artisan('network:poll', ['network' => 'tbm'])
        ->expectsOutputToContain('tbm: 1 read')
        ->assertSuccessful();

    Sleep::assertSequence([Sleep::for(2)->seconds()]);
    Http::assertSentCount(2);
});

it('carries on as unchanged when the second request fails, still ageing vehicles out', function (): void {
    Log::spy();
    Cache::put('vehicle-feed:tbm:etag', '"rt1"');
    Http::fakeSequence('rt.test/*')
        ->push('', 304)
        ->push('Service unavailable', 503);

    $this->artisan('network:poll', ['network' => 'tbm'])
        ->expectsOutputToContain('tbm: feed unchanged, 0 removed.')
        ->assertSuccessful();

    Log::shouldHaveReceived('info')->withArgs(fn (string $message): bool => $message === 'Vehicle feed of tbm could not be read again.');
    Log::shouldNotHaveReceived('warning');
});

it('sends what scheduled commands write to the scheduler\'s own log, the container\'s', function (): void {
    $events = collect(app(Schedule::class)->events())->filter(fn ($event): bool => str_contains((string) $event->command, 'network:'));

    expect($events)->toHaveCount(2)
        ->and($events->pluck('output')->unique()->all())->toBe(['/proc/1/fd/2'])
        ->and($events->every(fn ($event): bool => $event->shouldAppendOutput))->toBeTrue();
});
