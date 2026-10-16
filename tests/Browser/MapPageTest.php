<?php

declare(strict_types=1);

use App\Enums\TransportMode;
use App\Models\Line;
use App\Models\VehiclePosition;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;

beforeEach(function (): void {
    // Browser tests run against the built assets (`npm run build`), not the Vite dev server.
    Vite::useHotFile(storage_path('framework/no-hot-file'));
});

it('shows the map without accessibility issues', function (): void {
    visit('/')
        ->assertPresent('[data-map][data-state="loaded"] canvas')
        ->assertScript('document.documentElement.lang', 'fr')
        ->assertAttribute('[data-map] canvas', 'aria-label', 'Carte des véhicules TBM en circulation')
        ->assertPresent('button[aria-label="Zoom avant"]')
        ->assertNoJavaScriptErrors()
        // Level 3 counts every axe violation, down to minor ones (PRD: no axe violation).
        ->assertNoAccessibilityIssues(level: 3);
});

it('lets keyboard users skip the map to the text view', function (): void {
    visit('/')
        ->keys('html > body', 'Tab')
        ->assertScript("document.activeElement.getAttribute('href')", '#vue-texte')
        ->keys('a[href="#vue-texte"]', 'Enter')
        ->assertScript('document.activeElement.id', 'vue-texte')
        ->assertPresent('#vue-texte h2');
});

it('groups vehicles at low zoom and opens a group from the keyboard', function (): void {
    Line::query()->create(['id' => 'tbm:A', 'network' => 'tbm', 'short_name' => 'A', 'mode' => TransportMode::Tram, 'color' => '81057F', 'sync_id' => Str::uuid()]);

    foreach (['one' => 44.8400, 'two' => 44.8401, 'three' => 44.8402, 'paris' => 48.85] as $vehicle => $latitude) {
        VehiclePosition::query()->create([
            'vehicle_id' => "tbm:{$vehicle}", 'network' => 'tbm', 'latitude' => $latitude, 'longitude' => $vehicle === 'paris' ? 2.35 : -0.57,
            'line_id' => $vehicle === 'three' ? null : 'tbm:A', 'recorded_at' => now(), 'received_at' => now(),
        ]);
    }

    // Without stops the map shows the whole of France: the three Bordeaux vehicles form one group.
    visit('/')
        ->assertPresent('[data-map][data-vehicle-count="4"]')
        ->assertPresent('button[aria-label="3 véhicules, zoomer sur le groupe"]')
        ->assertNoAccessibilityIssues(level: 3)
        ->keys('button[aria-label="3 véhicules, zoomer sur le groupe"]', 'Enter')
        ->assertMissing('button[aria-label="3 véhicules, zoomer sur le groupe"]')
        ->assertNoJavaScriptErrors();
});
