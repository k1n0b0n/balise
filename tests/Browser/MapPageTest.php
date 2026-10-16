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

it('draws every vehicle without accessibility issues', function (): void {
    Line::query()->create(['id' => 'tbm:A', 'network' => 'tbm', 'short_name' => 'A', 'mode' => TransportMode::Tram, 'color' => '81057F', 'sync_id' => Str::uuid()]);

    foreach (['one' => 'tbm:A', 'two' => 'tbm:A', 'lineless' => null] as $vehicle => $line) {
        VehiclePosition::query()->create([
            'vehicle_id' => "tbm:{$vehicle}", 'network' => 'tbm', 'latitude' => 44.84, 'longitude' => -0.57,
            'line_id' => $line, 'recorded_at' => now(), 'received_at' => now(),
        ]);
    }

    visit('/')
        ->assertPresent('[data-map][data-vehicle-count="3"]')
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues(level: 3);
});
