<?php

declare(strict_types=1);

use App\Enums\TransportMode;
use App\Models\Line;
use App\Models\Stop;
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

it('opens a vehicle popup on click and closes it with Escape', function (): void {
    Line::query()->create(['id' => 'tbm:A', 'network' => 'tbm', 'short_name' => 'A', 'long_name' => 'Le Haillan Rostand / Floirac Dravemont', 'mode' => TransportMode::Tram, 'color' => '81057F', 'sync_id' => Str::uuid()]);
    VehiclePosition::query()->create([
        'vehicle_id' => 'tbm:1234', 'network' => 'tbm', 'latitude' => 44.84, 'longitude' => -0.57,
        'line_id' => 'tbm:A', 'recorded_at' => now()->subMinutes(3), 'received_at' => now(),
    ]);

    // Two stops around the vehicle: the map fits them, so the vehicle sits at the centre of the canvas.
    foreach (['west' => [44.83, -0.58], 'east' => [44.85, -0.56]] as $id => [$latitude, $longitude]) {
        Stop::query()->create(['id' => "tbm:{$id}", 'network' => 'tbm', 'name' => $id, 'latitude' => $latitude, 'longitude' => $longitude, 'sync_id' => Str::uuid()]);
    }

    visit('/')
        ->assertPresent('[data-map][data-vehicle-count="1"]')
        ->click('[data-map] canvas')
        ->assertSeeIn('[role="dialog"][aria-label="Véhicule 1234"]', 'A – Le Haillan Rostand / Floirac Dravemont')
        // Minutes, not seconds: the age stays the same however slow the browser is to open the popup.
        ->assertSeeIn('[role="dialog"]', 'il y a 3 min')
        ->assertNoAccessibilityIssues(level: 3)
        ->keys('[role="dialog"] button', 'Escape')
        ->assertMissing('[role="dialog"]')
        // The click had focused the canvas; Escape gives the focus back to it.
        ->assertScript("document.activeElement === document.querySelector('[data-map] canvas')", true)
        ->assertNoJavaScriptErrors();
});
