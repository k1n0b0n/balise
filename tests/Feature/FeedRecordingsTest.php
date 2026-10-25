<?php

declare(strict_types=1);

use App\Models\VehiclePosition as StoredPosition;
use App\Support\FeedRecordings;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Sleep;

beforeEach(function (): void {
    config([
        'networks.tbm.vehicles.url' => 'https://rt.test/tbm',
        'networks.tbm.catalog.url' => 'https://gtfs.test/tbm.zip',
        'networks.tbm.recordings.enabled' => true,
    ]);
    Storage::fake('recordings');
    Storage::fake('recording-archives');
    Sleep::fake();
});

/**
 * A TBM feed with one vehicle reported at that time, which the poll keeps as fresh.
 */
function freshFeed(CarbonImmutable $at): string
{
    return feedPayload([feedEntity('e1', ['lat' => 44.84, 'lon' => -0.57, 'id' => 'bus-1', 'timestamp' => $at->getTimestamp()])], $at->getTimestamp());
}

/**
 * Records a payload of one fresh vehicle at each time, given in Paris time, as the poll does.
 */
function recordAt(string ...$times): void
{
    foreach ($times as $time) {
        $at = CarbonImmutable::parse($time, 'Europe/Paris');
        app(FeedRecordings::class)->record('tbm', freshFeed($at), $at, 1, $at);
    }
}

/**
 * Paris times every 20 minutes from one to the other, both included: a network running all along.
 *
 * @return list<string>
 */
function running(string $from, string $to): array
{
    $times = [];
    for ($at = CarbonImmutable::parse($from); $at->lessThanOrEqualTo(CarbonImmutable::parse($to)); $at = $at->addMinutes(20)) {
        $times[] = $at->format('Y-m-d H:i');
    }

    return $times;
}

/**
 * Unpacks an archive the way its users will, with tar.
 */
function extracted(string $archive): string
{
    $directory = sys_get_temp_dir().'/'.uniqid('recording');
    mkdir($directory);
    exec('tar -xf '.escapeshellarg($archive).' -C '.escapeshellarg($directory));

    return $directory;
}

/**
 * @return list<string> the service days that have raw files
 */
function recordedDays(): array
{
    return array_map(basename(...), Storage::disk('recordings')->directories('tbm/days'));
}

it('keeps each new payload as received, in its service day (E5.1)', function (): void {
    $now = CarbonImmutable::parse('2026-10-21 10:00:00', 'Europe/Paris');
    $this->travelTo($now);
    $payload = freshFeed($now);
    Http::fake(['rt.test/*' => Http::response($payload, 200, ['ETag' => '"rt1"'])]);

    $this->artisan('network:poll', ['network' => 'tbm'])->assertSuccessful();

    expect(gzdecode((string) Storage::disk('recordings')->get('tbm/days/2026-10-21/vehicles/20261021T080000Z.pb.gz')))->toBe($payload);
});

it('keeps nothing of an unchanged feed nor of a feed without a fresh vehicle', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-10-21 03:00:00', 'Europe/Paris'));
    Cache::put('vehicle-feed:tbm:etag', '"rt1"');
    Http::fakeSequence('rt.test/*')
        ->push('', 304)
        ->push('', 304)
        ->push(feedPayload([]), 200, ['ETag' => '"rt2"']);

    $this->artisan('network:poll', ['network' => 'tbm'])->assertSuccessful();
    $this->artisan('network:poll', ['network' => 'tbm'])->assertSuccessful();

    expect(Storage::disk('recordings')->allFiles())->toBe([]);
});

it('keeps polling when a payload cannot be recorded', function (): void {
    Log::spy();
    // A file where the recordings' folder should be: nothing can be written under it.
    config(['filesystems.disks.recordings.root' => (string) tempnam(sys_get_temp_dir(), 'recordings')]);
    Storage::forgetDisk('recordings');
    $now = CarbonImmutable::parse('2026-10-21 10:00:00', 'Europe/Paris');
    $this->travelTo($now);
    Http::fake(['rt.test/*' => Http::response(freshFeed($now), 200, ['ETag' => '"rt1"'])]);

    $this->artisan('network:poll', ['network' => 'tbm'])->assertSuccessful();

    expect(StoredPosition::query()->count())->toBe(1);
    Log::shouldHaveReceived('warning')->withArgs(fn (string $message): bool => $message === 'Vehicle feed of tbm could not be recorded.');
});

it('records nothing while recordings are off', function (): void {
    config(['networks.tbm.recordings.enabled' => false]);
    $now = CarbonImmutable::parse('2026-10-21 10:00:00', 'Europe/Paris');
    $this->travelTo($now);
    Http::fake(['rt.test/*' => Http::response(freshFeed($now), 200, ['ETag' => '"rt1"'])]);

    $this->artisan('network:poll', ['network' => 'tbm'])->assertSuccessful();

    expect(Storage::disk('recordings')->allFiles())->toBe([]);
});

it('runs a service day from the first vehicle to the last, across midnight', function (): void {
    recordAt(...running('2026-10-21 04:30', '2026-10-22 01:30'));
    // Back after the night's gap: the next day.
    recordAt('2026-10-22 04:30');

    expect(recordedDays())->toBe(['2026-10-21', '2026-10-22'])
        ->and(Storage::disk('recordings')->files('tbm/days/2026-10-21/vehicles'))->toHaveCount(64)
        ->and(Storage::disk('recordings')->files('tbm/days/2026-10-22/vehicles'))->toHaveCount(1);
});

it('keeps one day across a gap within its date', function (): void {
    // The feed down for an hour at noon.
    recordAt('2026-10-21 11:00', '2026-10-21 12:00');

    expect(recordedDays())->toBe(['2026-10-21']);
});

it('cuts a network that never empties at its configured hour', function (): void {
    recordAt(...running('2026-10-21 22:00', '2026-10-22 04:20'));

    expect(recordedDays())->toBe(['2026-10-21', '2026-10-22'])
        ->and(Storage::disk('recordings')->files('tbm/days/2026-10-22/vehicles'))->toHaveCount(2);
});

it('archives a finished day with its catalogue, a manifest and a README, then deletes its raw files', function (): void {
    recordAt(...running('2026-10-21 04:30', '2026-10-22 01:30'));
    Storage::disk('recordings')->put('tbm/catalog.zip', 'the catalogue');

    $this->travelTo(CarbonImmutable::parse('2026-10-22 05:15', 'Europe/Paris'));
    $this->artisan('network:archive', ['network' => 'tbm'])
        ->expectsOutputToContain('tbm: archived 2026-10-21.')
        ->assertSuccessful();

    $content = extracted(Storage::disk('recording-archives')->path('tbm-2026-10-21.tar'));
    $manifest = json_decode((string) file_get_contents("{$content}/manifest.json"), associative: true);
    expect(is_file("{$content}/vehicles/20261021T023000Z.pb.gz") && is_file("{$content}/vehicles/20261021T233000Z.pb.gz"))->toBeTrue()
        ->and(file_get_contents("{$content}/catalog.zip"))->toBe('the catalogue')
        ->and($manifest)->toMatchArray(['service_day' => '2026-10-21', 'first' => '2026-10-21T02:30:00Z', 'last' => '2026-10-21T23:30:00Z', 'payloads' => 64, 'catalog' => true])
        ->and(file_get_contents("{$content}/README.md"))->toContain('Licence Ouverte')
        ->and(recordedDays())->toBe([]);

    $index = json_decode((string) Storage::disk('recording-archives')->get('index.json'), associative: true);
    expect($index['archives'])->toHaveCount(1)
        ->and($index['archives'][0])->toMatchArray(['file' => 'tbm-2026-10-21.tar', 'service_day' => '2026-10-21', 'payloads' => 64]);
});

it('leaves a running day and a day dated today for later', function (string $now, array $archived): void {
    recordAt(...running('2026-10-21 23:00', '2026-10-22 00:20'));

    $this->travelTo(CarbonImmutable::parse($now, 'Europe/Paris'));

    expect(app(FeedRecordings::class)->archive('tbm', CarbonImmutable::now()))->toBe($archived);
})->with([
    // Its last vehicle ten minutes ago: still running.
    'running past midnight' => ['2026-10-22 00:30', []],
    'empty for half an hour' => ['2026-10-22 00:50', ['2026-10-21']],
    // Archived at 23:00 the same day, the network may still be running: not before the next date.
    'dated today' => ['2026-10-21 23:00', []],
]);

it('catches up the days a missed night left, and keeps the last archives only', function (): void {
    config(['recordings.keep' => 2]);
    recordAt('2026-10-19 10:00', '2026-10-20 10:00', '2026-10-21 10:00');

    $this->travelTo(CarbonImmutable::parse('2026-10-22 05:15', 'Europe/Paris'));
    $archived = app(FeedRecordings::class)->archive('tbm', CarbonImmutable::now());

    $index = json_decode((string) Storage::disk('recording-archives')->get('index.json'), associative: true);
    expect($archived)->toBe(['2026-10-19', '2026-10-20', '2026-10-21'])
        ->and(Storage::disk('recording-archives')->files())->toBe(['index.json', 'tbm-2026-10-20.json', 'tbm-2026-10-20.tar', 'tbm-2026-10-21.json', 'tbm-2026-10-21.tar'])
        ->and(array_column($index['archives'], 'file'))->toBe(['tbm-2026-10-21.tar', 'tbm-2026-10-20.tar']);
});

it('keeps the catalogue it imports for the recordings, downloading it once even when unchanged', function (): void {
    $catalogue = gtfsArchive();
    Http::fake(['gtfs.test/*' => fn () => Http::response($catalogue, 200, ['ETag' => '"v1"'])]);

    config(['networks.tbm.recordings.enabled' => false]);
    $this->artisan('network:import', ['network' => 'tbm'])->assertSuccessful();
    expect(Storage::disk('recordings')->exists('tbm/catalog.zip'))->toBeFalse();

    // Turned on: the unchanged catalogue is downloaded again, once, so that the recordings have it.
    config(['networks.tbm.recordings.enabled' => true]);
    $this->artisan('network:import', ['network' => 'tbm'])->assertSuccessful();
    expect(Storage::disk('recordings')->get('tbm/catalog.zip'))->toBe($catalogue);

    $this->artisan('network:import', ['network' => 'tbm'])->assertSuccessful();
    Http::assertSent(fn ($request): bool => $request->hasHeader('If-None-Match', '"v1"'));
});
