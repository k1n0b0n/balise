<?php

declare(strict_types=1);

use App\Models\CatalogImport;
use App\Models\Station;
use App\Models\Stop;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * @param  array<string, array{0: string, 1: float, 2: float}>  $stations  id => [name, latitude, longitude]
 */
function stations(array $stations): void
{
    foreach ($stations as $id => [$name, $latitude, $longitude]) {
        Station::query()->create(['id' => $id, 'network' => 'tbm', 'name' => $name, 'latitude' => $latitude, 'longitude' => $longitude, 'sync_id' => Str::uuid()]);
    }
}

it('imports the stations and the station of each platform; a lone platform stands for itself (E7.1)', function (): void {
    config(['networks.tbm.catalog.url' => 'https://gtfs.test/tbm.zip']);
    Http::fake(['gtfs.test/*' => Http::response(gtfsArchive(), 200, ['ETag' => '"v1"'])]);

    $this->artisan('network:import', ['network' => 'tbm'])->assertSuccessful();

    // QUIN is a station with the platform QUIN_A; GARE is a platform without one; E1 is an entrance.
    expect(Station::query()->orderBy('id')->pluck('name', 'id')->all())->toBe(['tbm:GARE' => 'Gare Saint-Jean', 'tbm:QUIN' => 'Quinconces'])
        ->and(Stop::query()->orderBy('id')->pluck('station_id', 'id')->all())->toBe(['tbm:GARE' => 'tbm:GARE', 'tbm:QUIN_A' => 'tbm:QUIN']);
});

it('downloads an unchanged catalogue again while the network has no stations', function (): void {
    config(['networks.tbm.catalog.url' => 'https://gtfs.test/tbm.zip']);
    Http::fake(['gtfs.test/*' => fn () => Http::response(gtfsArchive(), 200, ['ETag' => '"v1"'])]);
    $this->artisan('network:import', ['network' => 'tbm'])->assertSuccessful();
    // The state of a server imported before E7.1.
    Station::query()->delete();

    $this->artisan('network:import', ['network' => 'tbm'])->assertSuccessful();

    expect(Station::query()->count())->toBe(2);
    Http::assertSent(fn ($request): bool => ! $request->hasHeader('If-None-Match'));
});

it('serves the stations as GeoJSON, unchanged until the next import', function (): void {
    stations(['tbm:QUIN' => ['Quinconces', 44.844780123, -0.573610456]]);
    CatalogImport::query()->create(['network' => 'tbm', 'etag' => 'v1', 'imported_at' => now()]);

    $etag = $this->getJson(route('stations', 'tbm'))
        ->assertOk()
        ->assertExactJson(['type' => 'FeatureCollection', 'features' => [
            ['type' => 'Feature', 'id' => 'tbm:QUIN', 'geometry' => ['type' => 'Point', 'coordinates' => [-0.57361, 44.84478]], 'properties' => ['name' => 'Quinconces']],
        ]])
        ->headers->get('ETag');

    $this->getJson(route('stations', 'tbm'), ['If-None-Match' => $etag])->assertNotModified();
    $this->getJson(route('stations', 'nowhere'))->assertNotFound();
});

it('lists the stations found without JavaScript, accents and case ignored, exact names first', function (): void {
    stations([
        'tbm:QUIN' => ['Quinconces', 44.84, -0.57],
        'tbm:QUIN2' => ['Quinconces - Cours du Médoc', 44.85, -0.57],
        'tbm:MED' => ['Médoc', 44.86, -0.58],
    ]);

    $this->get('/?arret=medoc')
        ->assertOk()
        ->assertSeeTextInOrder(['Arrêts trouvés pour « medoc »', 'Médoc', 'Quinconces - Cours du Médoc']);

    $this->get('/?arret=nulle+part')->assertOk()->assertSeeText('Aucun arrêt ne correspond.');
    $this->get('/')->assertOk()->assertDontSeeText('Arrêts trouvés');
});
