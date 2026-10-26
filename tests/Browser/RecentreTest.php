<?php

declare(strict_types=1);

use App\Actions\RecordPositions;
use App\Enums\TransportMode;
use App\Ingestion\VehiclePosition as IngestionPosition;
use App\Models\Line;
use App\Models\Station;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

/**
 * How far, in pixels, the centre of the open popup is from the centre of the map, horizontally.
 */
const POPUP_OFF_CENTRE = "(() => { const popup = document.querySelector('.maplibregl-popup').getBoundingClientRect(); const map = document.querySelector('[data-map]').getBoundingClientRect(); return Math.round(Math.abs(popup.x + popup.width / 2 - (map.x + map.width / 2))); })()";

it('keeps the map still while the chosen vehicle moves, and brings it back with the recentre button (E3.4)', function (): void {
    config(['map.refresh_every_seconds' => 1]);
    Line::query()->create(['id' => 'tbm:A', 'network' => 'tbm', 'short_name' => 'A', 'mode' => TransportMode::Tram, 'color' => '81057F', 'sync_id' => Str::uuid()]);
    $record = fn (float $longitude) => app(RecordPositions::class)->handle('tbm', [
        new IngestionPosition('tbm:1234', 44.84, $longitude, now()->toDateTimeImmutable(), 'tbm:A'),
    ], CarbonImmutable::now());
    $record(-0.57);

    $page = visit('/')
        ->assertPresent('[data-map][data-vehicle-count="1"]')
        ->assertMissing('[data-recentre]')
        ->keys('[data-vehicle="tbm:1234"] button', 'Enter')
        ->assertPresent('[role="dialog"]')
        ->assertAttribute('[data-recentre]', 'aria-label', 'Recentrer la carte sur le véhicule 1234')
        // The opening brings the vehicle to the centre once.
        ->wait(1);
    expect($page->script(POPUP_OFF_CENTRE))->toBeLessThan(30);

    // A few hundred metres east: the popup goes with its vehicle, the map does not.
    $record(-0.565);
    $page->wait(2);
    expect($page->script(POPUP_OFF_CENTRE))->toBeGreaterThan(100);

    $page->click('[data-recentre]')->wait(1);
    expect($page->script(POPUP_OFF_CENTRE))->toBeLessThan(30);

    $page->keys('[role="dialog"] button', 'Escape')
        ->assertMissing('[data-recentre]')
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues(level: 3);
});

it('recentres on the station whose popup is open', function (): void {
    Station::query()->create(['id' => 'tbm:QUIN', 'network' => 'tbm', 'name' => 'Quinconces', 'latitude' => 44.84478, 'longitude' => -0.57361, 'sync_id' => Str::uuid()]);

    visit('/')
        ->assertPresent('[data-map][data-state="loaded"]')
        ->type('#arret', 'quinconces')
        ->keys('#arret', 'Enter')
        ->assertSeeIn('.station-popup', 'Quinconces')
        ->assertAttribute('[data-recentre]', 'aria-label', 'Recentrer la carte sur l’arrêt Quinconces')
        ->assertNoJavaScriptErrors();
});
