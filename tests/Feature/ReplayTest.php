<?php

declare(strict_types=1);

use App\Ingestion\VehicleFeeds;
use App\Models\Line;
use App\Models\VehiclePosition as StoredPosition;
use App\Support\Replays;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Sleep;

beforeEach(function (): void {
    config([
        'networks.tbm.catalog.url' => 'https://gtfs.test/tbm.zip',
        'recordings.source_url' => null,
    ]);
    Storage::fake('replays');
    Sleep::fake();
});

/**
 * Lays out a recorded day of TBM as an unpacked archive: one payload per Paris time, its one vehicle at that
 * latitude, and the catalogue of the fixtures.
 *
 * @param  array<string, float>  $latitudes  Paris time => latitude
 */
function recordedDay(string $directory, string $day, array $latitudes): void
{
    $times = [];
    foreach ($latitudes as $time => $latitude) {
        $at = CarbonImmutable::parse("{$day} {$time}", 'Europe/Paris');
        $times[] = $at;
        $payload = feedPayload([feedEntity('e1', ['lat' => $latitude, 'lon' => -0.57, 'id' => 'bus-1', 'route' => 'A', 'timestamp' => $at->getTimestamp()])], $at->getTimestamp());
        @mkdir("{$directory}/vehicles", recursive: true);
        file_put_contents("{$directory}/vehicles/".$at->setTimezone('UTC')->format('Ymd\THis\Z').'.pb.gz', gzencode($payload));
    }

    file_put_contents("{$directory}/catalog.zip", gtfsArchive());
    file_put_contents("{$directory}/manifest.json", json_encode([
        'network' => 'tbm',
        'service_day' => $day,
        'first' => $times[0]->toIso8601ZuluString(),
        'last' => end($times)->toIso8601ZuluString(),
        'payloads' => count($times),
    ]));
}

/**
 * Starts replaying 2026-10-21 from 12:00, recorded at 12:00:00, 12:00:20 and 12:00:40, at that moment.
 */
function replayingFromNoon(CarbonImmutable $startedAt): void
{
    recordedDay(app(Replays::class)->directory('tbm', '2026-10-21'), '2026-10-21', ['12:00:00' => 44.81, '12:00:20' => 44.82, '12:00:40' => 44.83]);
    app(Replays::class)->start('tbm', '2026-10-21', '12:00', $startedAt);
}

it('replays the payload recorded at the replayed time, moved to now (E5.2)', function (): void {
    $startedAt = CarbonImmutable::parse('2026-10-25 21:00:00', 'Europe/Paris');
    replayingFromNoon($startedAt);
    // 30 s into the replay: 12:00:30 of that day, so the payload of 12:00:20, ten seconds old.
    $this->travelTo($startedAt->addSeconds(30));

    $this->artisan('network:poll', ['network' => 'tbm'])->assertSuccessful();

    $position = StoredPosition::query()->sole();
    expect(round($position->latitude, 5))->toBe(44.82)
        ->and($position->line_id)->toBe('tbm:A')
        ->and($position->recorded_at->getTimestamp())->toBe($startedAt->addSeconds(20)->getTimestamp());
});

it('answers an unchanged payload as unchanged, and loops over the day', function (): void {
    $startedAt = CarbonImmutable::parse('2026-10-25 21:00:00', 'Europe/Paris');
    replayingFromNoon($startedAt);
    $feed = fn () => app(VehicleFeeds::class)->for('tbm');

    $this->travelTo($startedAt->addSeconds(5));
    $first = $feed()->fetch(null);
    $this->travelTo($startedAt->addSeconds(15));

    expect(round((float) $first?->positions[0]->latitude, 5))->toBe(44.81)
        ->and($feed()->fetch($first?->etag))->toBeNull();

    // The day lasts its payloads and one interval: a minute here, then it starts again.
    $this->travelTo($startedAt->addSeconds(65));
    expect(round((float) $feed()->fetch(null)?->positions[0]->latitude, 5))->toBe(44.81);
});

it('downloads the latest day from the site named on GitHub, imports its catalogue and replays it', function (): void {
    $source = sys_get_temp_dir().'/'.uniqid('archive');
    recordedDay($source, '2026-10-21', ['12:00:00' => 44.81]);
    $tar = "{$source}.tar";
    exec('tar -cf '.escapeshellarg($tar).' -C '.escapeshellarg($source).' .');
    Http::fake([
        'api.github.com/repos/k1n0b0n/balise' => Http::response(['homepage' => 'https://balise.example.org']),
        'balise.example.org/recordings/index.json' => Http::response(['archives' => [
            ['file' => 'tbm-2026-10-21.tar', 'network' => 'tbm', 'service_day' => '2026-10-21'],
            ['file' => 'tbm-2026-10-20.tar', 'network' => 'tbm', 'service_day' => '2026-10-20'],
        ]]),
        'balise.example.org/recordings/tbm-2026-10-21.tar' => Http::response((string) file_get_contents($tar)),
    ]);

    $this->artisan('network:replay', ['network' => 'tbm', '--from' => '08:30'])
        ->expectsOutputToContain('tbm: replaying 2026-10-21 from 08:30')
        ->assertSuccessful();

    expect(app(Replays::class)->current('tbm'))->toMatchArray(['day' => '2026-10-21', 'from' => '08:30'])
        ->and(Line::query()->whereKey('tbm:A')->exists())->toBeTrue();
});

it('says why it cannot download a day', function (?string $sourceUrl, string $message): void {
    config(['recordings.source_url' => $sourceUrl]);
    // Built here, not in the dataset: the HTTP client does not exist yet when datasets are read.
    Http::fake([
        'api.github.com/*' => Http::response(['homepage' => null]),
        'recordings.test/index.json' => Http::response(['archives' => []]),
    ]);

    $this->artisan('network:replay', ['network' => 'tbm', 'day' => '2026-10-19'])
        ->expectsOutputToContain($message)
        ->assertFailed();

    expect(app(Replays::class)->current('tbm'))->toBeNull();
})->with([
    'no source' => [null, 'set RECORDINGS_SOURCE_URL, or the Website field'],
    'an unknown day' => ['https://recordings.test', 'No recording of tbm on 2026-10-19'],
]);

it('goes back to the live feed and its catalogue', function (): void {
    replayingFromNoon(CarbonImmutable::now());
    Http::fake(['gtfs.test/*' => Http::response(gtfsArchive(), 200, ['ETag' => '"v1"'])]);

    $this->artisan('network:replay', ['network' => 'tbm', '--stop' => true])->assertSuccessful();

    expect(app(Replays::class)->current('tbm'))->toBeNull();
    Http::assertSent(fn ($request): bool => $request->url() === 'https://gtfs.test/tbm.zip');
});

it('never replays in production', function (): void {
    app()->detectEnvironment(fn (): string => 'production');

    $this->artisan('network:replay', ['network' => 'tbm'])
        ->expectsOutputToContain('Replays run locally only.')
        ->assertFailed();
});

it('leaves a replaying network\'s catalogue alone in the daily import', function (): void {
    replayingFromNoon(CarbonImmutable::now());
    Http::fake();

    $this->artisan('network:import', ['network' => 'tbm'])
        ->expectsOutputToContain('tbm: replaying 2026-10-21, its catalogue is kept.')
        ->assertSuccessful();

    Http::assertNothingSent();
});

it('says on the page that it shows a replay, of which day and at which time', function (): void {
    $startedAt = CarbonImmutable::parse('2026-10-25 21:00:00', 'Europe/Paris');
    replayingFromNoon($startedAt);
    $this->travelTo($startedAt->addSeconds(30));

    $this->get('/')
        ->assertOk()
        ->assertSeeText('Rediffusion du mercredi 21 octobre 2026')
        ->assertSee('data-replay-clock', false)
        ->assertSeeText('12:00');
});

it('shows no replay banner on the live network', function (): void {
    $this->get('/')->assertOk()->assertDontSee('data-replay', false);
});
