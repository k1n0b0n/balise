<?php

declare(strict_types=1);

use App\Enums\TransportMode;
use App\Models\CatalogImport;
use App\Models\Line;
use App\Models\Stop;
use App\Support\NetworkShapes;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    config(['networks.tbm.catalog.url' => 'https://gtfs.test/tbm.zip']);
});

function storedShapes(): string
{
    return app(NetworkShapes::class)->read('tbm') ?? '';
}

it('imports lines and stops with ids namespaced by network', function (): void {
    Http::fake(['gtfs.test/*' => Http::response(gtfsArchive(), 200, ['ETag' => '"v1"'])]);

    $this->artisan('network:import', ['network' => 'tbm'])
        ->expectsOutputToContain('tbm: 4 lines and 2 stops imported.')
        ->assertSuccessful();

    $tram = Line::query()->findOrFail('tbm:A');
    expect($tram->network)->toBe('tbm')
        ->and($tram->short_name)->toBe('A')
        ->and($tram->long_name)->toBe('Tram A')
        ->and($tram->mode)->toBe(TransportMode::Tram)
        ->and($tram->color)->toBe('7D1A5C')
        ->and($tram->text_color)->toBe('FFFFFF');

    expect(Line::query()->findOrFail('tbm:S12'))
        ->color->toBeNull()
        ->text_color->toBeNull();

    expect(Line::query()->findOrFail('tbm:BAT'))
        ->short_name->toBe('BatCub')
        ->mode->toBe(TransportMode::Ferry)
        ->color->toBe('FFCC00');

    expect(Stop::query()->orderBy('id')->pluck('id')->all())->toBe(['tbm:GARE', 'tbm:QUIN_A'])
        ->and(Stop::query()->findOrFail('tbm:QUIN_A')->latitude)->toBe(44.844712);

    expect(CatalogImport::query()->findOrFail('tbm')->etag)->toBe('"v1"');
});

it('gives the same result when run twice and removes what left the feed', function (): void {
    $withoutLianes = "route_id,agency_id,route_short_name,route_long_name,route_type,route_color,route_text_color\nA,1,A,Tram A,0,7D1A5C,FFFFFF\n";

    Http::fakeSequence('gtfs.test/*')
        ->push(gtfsArchive(), 200, ['ETag' => '"v1"'])
        ->push(gtfsArchive(), 200, ['ETag' => '"v2"'])
        ->push(gtfsArchive(replace: ['routes.txt' => $withoutLianes]), 200, ['ETag' => '"v3"']);

    $this->artisan('network:import', ['network' => 'tbm'])->assertSuccessful();
    $this->artisan('network:import', ['network' => 'tbm'])->assertSuccessful();

    expect(Line::query()->count())->toBe(4)
        ->and(Stop::query()->count())->toBe(2);

    $this->artisan('network:import', ['network' => 'tbm'])->assertSuccessful();

    expect(Line::query()->pluck('id')->all())->toBe(['tbm:A']);
});

it('skips the import when the catalogue has not changed', function (): void {
    Http::fakeSequence('gtfs.test/*')
        ->push(gtfsArchive(), 200, ['ETag' => '"v1"'])
        ->push('', 304);

    $this->artisan('network:import', ['network' => 'tbm'])->assertSuccessful();
    $this->artisan('network:import', ['network' => 'tbm'])
        ->expectsOutputToContain('tbm: catalogue unchanged since the last import.')
        ->assertSuccessful();

    Http::assertSent(fn (Request $request): bool => $request->header('If-None-Match') === ['"v1"']);
    expect(Line::query()->count())->toBe(4);
});

it('keeps the current catalogue when the download fails', function (): void {
    Http::fakeSequence('gtfs.test/*')
        ->push(gtfsArchive(), 200, ['ETag' => '"v1"'])
        ->push('Service unavailable', 503)
        ->push(gtfsArchive(replace: ['stops.txt' => null]), 200, ['ETag' => '"v2"']);

    $this->artisan('network:import', ['network' => 'tbm'])->assertSuccessful();

    expect(fn () => $this->artisan('network:import', ['network' => 'tbm'])->run())->toThrow(Exception::class);
    expect(fn () => $this->artisan('network:import', ['network' => 'tbm'])->run())->toThrow(RuntimeException::class, 'no stops.txt');

    expect(Line::query()->count())->toBe(4)
        ->and(Stop::query()->count())->toBe(2)
        ->and(CatalogImport::query()->findOrFail('tbm')->etag)->toBe('"v1"');
});

it('schedules a daily import for every network', function (): void {
    $commands = collect(app(Schedule::class)->events())
        ->map(fn ($event): string => (string) $event->command)
        ->filter(fn (string $command): bool => str_contains($command, 'network:import'));

    expect($commands)->toHaveCount(1)
        ->and($commands->first())->toContain("network:import 'tbm'");
});

it('imports every configured network when none is named, as a deploy does', function (): void {
    Http::fake(['gtfs.test/*' => Http::response(gtfsArchive(), 200, ['ETag' => '"v1"'])]);

    $this->artisan('network:import')
        ->expectsOutputToContain('tbm: 4 lines and 2 stops imported.')
        ->assertSuccessful();

    expect(CatalogImport::query()->pluck('network')->all())->toBe(array_keys(config('networks')));
});

it('imports the other networks when one of them fails, and reports the failure', function (): void {
    config(['networks.down' => array_replace_recursive(config('networks.tbm'), ['catalog' => ['url' => 'https://down.test/gtfs.zip']])]);
    Http::fake([
        'down.test/*' => Http::response('', 503),
        'gtfs.test/*' => Http::response(gtfsArchive(), 200, ['ETag' => '"v1"']),
    ]);
    Exceptions::fake();

    $this->artisan('network:import')
        ->expectsOutputToContain('tbm: 4 lines and 2 stops imported.')
        ->expectsOutputToContain('down: ')
        ->assertFailed();

    Exceptions::assertReportedCount(1);
    expect(CatalogImport::query()->pluck('network')->all())->toBe(['tbm']);
});

it('rejects a network that is not configured', function (): void {
    $this->artisan('network:import', ['network' => 'nowhere'])
        ->expectsOutputToContain('Configured networks: tbm.')
        ->assertExitCode(2);
});

it('keeps the current catalogue when a file is empty or has no lines', function (): void {
    Http::fakeSequence('gtfs.test/*')
        ->push(gtfsArchive(), 200, ['ETag' => '"v1"'])
        ->push(gtfsArchive(replace: ['routes.txt' => '']), 200, ['ETag' => '"v2"'])
        ->push(gtfsArchive(replace: ['routes.txt' => "route_id,route_short_name,route_type\n"]), 200, ['ETag' => '"v3"']);

    $this->artisan('network:import', ['network' => 'tbm'])->assertSuccessful();

    expect(fn () => $this->artisan('network:import', ['network' => 'tbm'])->run())->toThrow(RuntimeException::class, 'empty routes.txt');
    expect(fn () => $this->artisan('network:import', ['network' => 'tbm'])->run())->toThrow(RuntimeException::class, 'has no lines');

    expect(Line::query()->count())->toBe(4)
        ->and(Stop::query()->count())->toBe(2)
        ->and(CatalogImport::query()->findOrFail('tbm')->etag)->toBe('"v1"');
});

it('reads rows that have more fields than the header', function (): void {
    $trailingComma = "route_id,route_short_name,route_type\nA,A,0,\n";

    Http::fake(['gtfs.test/*' => Http::response(gtfsArchive(replace: ['routes.txt' => $trailingComma]), 200)]);

    $this->artisan('network:import', ['network' => 'tbm'])->assertSuccessful();

    expect(Line::query()->pluck('id')->all())->toBe(['tbm:A']);
});

it('leaves no temporary file behind when the download fails', function (): void {
    $before = glob(sys_get_temp_dir().'/gtfs*') ?: [];

    Http::fake(fn () => throw new ConnectionException('Connection timed out'));

    expect(fn () => $this->artisan('network:import', ['network' => 'tbm'])->run())->toThrow(ConnectionException::class);
    expect(glob(sys_get_temp_dir().'/gtfs*') ?: [])->toBe($before);
});

it('writes the network shapes, one simplified feature per line', function (): void {
    Http::fake(['gtfs.test/*' => Http::response(gtfsArchive(), 200, ['ETag' => '"v1"'])]);

    $this->artisan('network:import', ['network' => 'tbm'])->assertSuccessful();

    $shapes = json_decode(storedShapes(), true);

    // SA_1 is followed by two trips and read in its sequence order; S1 loses the point a metre off its line; SG belongs to a line the catalogue lacks.
    expect($shapes['features'])->toBe([
        ['type' => 'Feature', 'properties' => ['line' => 'tbm:01'], 'geometry' => ['type' => 'MultiLineString', 'coordinates' => [
            [[-0.58, 44.84], [-0.57, 44.84]],
        ]]],
        ['type' => 'Feature', 'properties' => ['line' => 'tbm:A'], 'geometry' => ['type' => 'MultiLineString', 'coordinates' => [
            [[-0.573, 44.845], [-0.5725, 44.844], [-0.571, 44.843], [-0.57, 44.842]],
            [[-0.57, 44.842], [-0.573, 44.845]],
        ]]],
    ]);
    expect(CatalogImport::query()->findOrFail('tbm')->shapes_fingerprint)->toBe(hash('xxh128', storedShapes()));
});

it('rewrites the shapes only when they change, not on every new archive', function (): void {
    $movedShapes = "shape_id,shape_pt_lat,shape_pt_lon,shape_pt_sequence\nSA_1,44.84,-0.57,1\nSA_1,44.841,-0.571,2\n";

    Http::fakeSequence('gtfs.test/*')
        ->push(gtfsArchive(), 200, ['ETag' => '"v1"'])
        ->push(gtfsArchive(), 200, ['ETag' => '"v2"'])
        ->push(gtfsArchive(replace: ['shapes.txt' => $movedShapes]), 200, ['ETag' => '"v3"']);

    $this->artisan('network:import', ['network' => 'tbm'])->assertSuccessful();
    $fingerprint = CatalogImport::query()->findOrFail('tbm')->shapes_fingerprint;
    // Overwritten behind the import's back: a new write would replace it.
    CatalogImport::query()->whereKey('tbm')->update(['shapes' => 'untouched']);

    $this->artisan('network:import', ['network' => 'tbm'])->assertSuccessful();

    expect(storedShapes())->toBe('untouched')
        ->and(CatalogImport::query()->findOrFail('tbm')->shapes_fingerprint)->toBe($fingerprint);

    $this->artisan('network:import', ['network' => 'tbm'])->assertSuccessful();

    expect(storedShapes())->toContain('[[-0.57,44.84],[-0.571,44.841]]')
        ->and(CatalogImport::query()->findOrFail('tbm')->shapes_fingerprint)->not->toBe($fingerprint);
});

it('downloads the unchanged archive again when the shapes were lost, as a deploy found them', function (): void {
    Http::fakeSequence('gtfs.test/*')
        ->push(gtfsArchive(), 200, ['ETag' => '"v1"'])
        // Asked without the ETag, the provider sends the same archive again rather than a 304.
        ->push(gtfsArchive(), 200, ['ETag' => '"v1"']);

    $this->artisan('network:import', ['network' => 'tbm'])->assertSuccessful();
    CatalogImport::query()->whereKey('tbm')->update(['shapes' => null]);
    $this->artisan('network:import', ['network' => 'tbm'])->assertSuccessful();

    Http::assertSentInOrder([
        fn (Request $request): bool => $request->header('If-None-Match') === [],
        fn (Request $request): bool => $request->header('If-None-Match') === [],
    ]);
    expect(storedShapes())->toContain('tbm:A');
});

it('writes no line for a catalogue without shapes', function (): void {
    Http::fake(['gtfs.test/*' => Http::response(gtfsArchive(replace: ['shapes.txt' => null]), 200, ['ETag' => '"v1"'])]);

    $this->artisan('network:import', ['network' => 'tbm'])->assertSuccessful();

    expect(json_decode(storedShapes(), true))->toBe(['type' => 'FeatureCollection', 'features' => []]);
});

it('leaves a gap where the source jumps across the city', function (): void {
    // About 1.1 km between the second and third points, turning a right angle: no street is drawn there.
    $jumping = "shape_id,shape_pt_lat,shape_pt_lon,shape_pt_sequence\nSA_1,44.840,-0.580,1\nSA_1,44.840,-0.579,2\nSA_1,44.850,-0.579,3\nSA_1,44.850,-0.578,4\n";

    Http::fake(['gtfs.test/*' => Http::response(gtfsArchive(replace: ['shapes.txt' => $jumping]), 200, ['ETag' => '"v1"'])]);

    $this->artisan('network:import', ['network' => 'tbm'])->assertSuccessful();

    expect(json_decode(storedShapes(), true)['features'][0]['geometry']['coordinates'])->toBe([
        [[-0.579, 44.85], [-0.578, 44.85]],
        [[-0.58, 44.84], [-0.579, 44.84]],
    ]);
});

it('downloads the archive again when the shapes step failed', function (): void {
    Http::fakeSequence('gtfs.test/*')
        ->push(gtfsArchive(), 200, ['ETag' => '"v1"'])
        ->push(gtfsArchive(replace: ['trips.txt' => null]), 200, ['ETag' => '"v2"'])
        ->push(gtfsArchive(), 200, ['ETag' => '"v2"']);

    $this->artisan('network:import', ['network' => 'tbm'])->assertSuccessful();

    expect(fn () => $this->artisan('network:import', ['network' => 'tbm'])->run())->toThrow(RuntimeException::class, 'no trips.txt');
    expect(CatalogImport::query()->findOrFail('tbm')->etag)->toBe('"v1"');

    $this->artisan('network:import', ['network' => 'tbm'])->assertSuccessful();

    Http::assertSentInOrder([
        fn (Request $request): bool => $request->header('If-None-Match') === [],
        fn (Request $request): bool => $request->header('If-None-Match') === ['"v1"'],
        fn (Request $request): bool => $request->header('If-None-Match') === ['"v1"'],
    ]);
    expect(CatalogImport::query()->findOrFail('tbm')->etag)->toBe('"v2"');
});

it('downloads the archive again when the catalogue has no shapes yet', function (): void {
    CatalogImport::query()->create(['network' => 'tbm', 'etag' => '"v1"', 'imported_at' => now()]);
    Http::fake(['gtfs.test/*' => Http::response(gtfsArchive(), 200, ['ETag' => '"v1"'])]);

    $this->artisan('network:import', ['network' => 'tbm'])->assertSuccessful();

    Http::assertSent(fn (Request $request): bool => $request->header('If-None-Match') === []);
    expect(app(NetworkShapes::class)->has('tbm'))->toBeTrue();
});

it('keeps a long step that goes straight on, such as a bridge', function (): void {
    // About 790 m between the second and third points, on the same heading as the rest.
    $straight = "shape_id,shape_pt_lat,shape_pt_lon,shape_pt_sequence\nSA_1,44.840,-0.580,1\nSA_1,44.840,-0.579,2\nSA_1,44.840,-0.569,3\nSA_1,44.840,-0.568,4\n";

    Http::fake(['gtfs.test/*' => Http::response(gtfsArchive(replace: ['shapes.txt' => $straight]), 200, ['ETag' => '"v1"'])]);

    $this->artisan('network:import', ['network' => 'tbm'])->assertSuccessful();

    expect(json_decode(storedShapes(), true)['features'][0]['geometry']['coordinates'])->toBe([
        [[-0.58, 44.84], [-0.568, 44.84]],
    ]);
});
