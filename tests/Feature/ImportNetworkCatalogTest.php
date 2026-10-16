<?php

declare(strict_types=1);

use App\Enums\TransportMode;
use App\Models\CatalogImport;
use App\Models\Line;
use App\Models\Stop;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    config(['networks.tbm.catalog.url' => 'https://gtfs.test/tbm.zip']);
});

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

it('rejects a network that is not configured', function (): void {
    $this->artisan('network:import', ['network' => 'nowhere'])
        ->expectsOutputToContain('Configured networks: tbm.')
        ->assertExitCode(2);
});
