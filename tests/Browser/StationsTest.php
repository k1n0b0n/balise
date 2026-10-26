<?php

declare(strict_types=1);

use App\Models\Station;
use Illuminate\Support\Str;

beforeEach(function (): void {
    foreach (['tbm:QUIN' => ['Quinconces', 44.84478, -0.57361], 'tbm:GARE' => ['Gare Saint-Jean', 44.82581, -0.55639]] as $id => [$name, $latitude, $longitude]) {
        Station::query()->create(['id' => $id, 'network' => 'tbm', 'name' => $name, 'latitude' => $latitude, 'longitude' => $longitude, 'sync_id' => Str::uuid()]);
    }
});

it('finds a station by its name, centres the map on it and says so, without accessibility issues (E7.1)', function (): void {
    visit('/')
        ->assertPresent('[data-map][data-state="loaded"]')
        ->type('#arret', 'quinconces')
        ->keys('#arret', 'Enter')
        ->assertSeeIn('[data-station-status]', 'Carte centrée sur l’arrêt Quinconces.')
        ->assertPresent('[data-map][data-station-count="2"]')
        ->assertSeeIn('.station-popup', 'Quinconces')
        // The focus stays in the field, so that the status is what a screen reader says.
        ->assertScript('document.activeElement.id', 'arret')
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues(level: 3);
});

it('says when no station matches', function (): void {
    visit('/')
        ->assertPresent('[data-map][data-state="loaded"]')
        ->type('#arret', 'nulle part')
        ->keys('#arret', 'Enter')
        ->assertSeeIn('[data-station-status]', 'Aucun arrêt ne correspond à « nulle part ».')
        ->assertMissing('.station-popup')
        ->assertNoJavaScriptErrors();
});
