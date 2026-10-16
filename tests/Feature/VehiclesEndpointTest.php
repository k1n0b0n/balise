<?php

declare(strict_types=1);

use App\Actions\RecordPositions;
use App\Enums\TransportMode;
use App\Ingestion\VehiclePosition;
use App\Models\Line;
use App\Support\PositionChangeLog;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

beforeEach(function (): void {
    $this->now = CarbonImmutable::parse('2026-10-16 12:00:00');
    $this->travelTo($this->now);

    foreach (['tbm:A' => ['A', TransportMode::Tram, 'FFD200'], 'tbm:B' => ['B', TransportMode::Bus, '00387b'], 'tbm:12' => ['12', TransportMode::Bus, null]] as $id => [$name, $mode, $colour]) {
        Line::query()->create(['id' => $id, 'network' => 'tbm', 'short_name' => $name, 'mode' => $mode, 'color' => $colour, 'sync_id' => Str::uuid()]);
    }
});

/**
 * Record a poll of the TBM feed, one position per vehicle id => line id.
 *
 * @param  array<string, ?string>  $vehicles
 */
function poll(array $vehicles, float $latitude = 44.84): void
{
    $now = CarbonImmutable::now();
    $positions = array_map(
        fn (string $vehicle, ?string $line): VehiclePosition => new VehiclePosition("tbm:{$vehicle}", $latitude, -0.57, $now->toDateTimeImmutable(), $line),
        array_keys($vehicles),
        $vehicles,
    );

    app(RecordPositions::class)->handle('tbm', $positions, $now);
}

/**
 * @return TestResponse<JsonResponse>
 */
function vehicles(?int $since = null): TestResponse
{
    return test()->getJson(route('vehicles', ['network' => 'tbm', 'since' => $since]));
}

it('returns every vehicle with its line, mode and colour', function (): void {
    poll(['yellow' => 'tbm:A', 'blue' => 'tbm:B', 'plain' => 'tbm:12', 'lineless' => null, 'unknown' => 'tbm:gone']);

    $response = vehicles()->assertOk()->assertJsonPath('full', true)->assertJsonPath('removed', []);

    expect(collect($response->json('vehicles'))->keyBy('id')->map(fn (array $vehicle): array => [$vehicle['line'], $vehicle['mode'], $vehicle['colour']])->all())->toEqual([
        'tbm:yellow' => ['A', 'tram', '#FFD200'],
        'tbm:blue' => ['B', 'bus', '#00387B'],
        'tbm:plain' => ['12', 'bus', Line::NEUTRAL_COLOUR],
        'tbm:lineless' => [null, null, Line::NEUTRAL_COLOUR],
        'tbm:unknown' => [null, null, Line::NEUTRAL_COLOUR],
    ]);
});

it('sends only what changed since the browser’s version', function (): void {
    poll(['still' => 'tbm:A', 'moving' => 'tbm:A', 'leaving' => 'tbm:B']);
    $version = vehicles()->json('version');

    $this->travel(20)->seconds();
    poll(['still' => 'tbm:A', 'moving' => 'tbm:A', 'leaving' => 'tbm:B'], latitude: 44.85);
    $this->travel(20)->seconds();
    poll(['still' => 'tbm:A', 'moving' => 'tbm:A', 'arriving' => null], latitude: 44.85);

    $this->travel(10)->minutes();
    poll(['still' => 'tbm:A', 'moving' => 'tbm:A', 'arriving' => null], latitude: 44.86);

    vehicles($version)
        ->assertOk()
        ->assertJsonPath('full', false)
        ->assertJsonPath('version', app(PositionChangeLog::class)->version('tbm'))
        ->assertJsonPath('removed', ['tbm:leaving'])
        ->assertJsonCount(3, 'vehicles');
});

it('answers 304 without a body when the browser is up to date', function (): void {
    poll(['bus' => 'tbm:A']);
    $etag = vehicles()->assertOk()->headers->get('ETag');

    $this->getJson(route('vehicles', ['network' => 'tbm']), ['If-None-Match' => $etag])
        ->assertNotModified()
        ->assertContent('');

    $this->travel(20)->seconds();
    poll(['bus' => 'tbm:A'], latitude: 44.85);

    $this->getJson(route('vehicles', ['network' => 'tbm']), ['If-None-Match' => $etag])->assertOk();
});

it('sends everything again to a browser that fell too far behind', function (): void {
    poll(['bus' => 'tbm:A']);
    $version = vehicles()->json('version');

    foreach (range(1, PositionChangeLog::KEPT_VERSIONS) as $step) {
        $this->travel(20)->seconds();
        poll(['bus' => 'tbm:A'], latitude: 44.84 + $step / 1000);
    }

    vehicles($version)->assertOk()->assertJsonPath('full', true)->assertJsonCount(1, 'vehicles');
    vehicles(123)->assertOk()->assertJsonPath('full', true);
});

it('does not know other networks', function (): void {
    $this->getJson(route('vehicles', ['network' => 'nowhere']))->assertNotFound();
});
