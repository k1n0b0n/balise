<?php

declare(strict_types=1);

use App\Enums\TransportMode;
use App\Models\CatalogImport;
use App\Models\Line;
use App\Models\Stop;
use App\Models\VehiclePosition;
use Illuminate\Support\Facades\Storage;
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

it('draws the network lines under the vehicles once the first positions are in', function (): void {
    Storage::fake('local');
    Line::query()->create(['id' => 'tbm:A', 'network' => 'tbm', 'short_name' => 'A', 'mode' => TransportMode::Tram, 'color' => '81057F', 'sync_id' => Str::uuid()]);
    Storage::disk('local')->put('shapes/tbm.json', (string) json_encode(['type' => 'FeatureCollection', 'features' => [
        ['type' => 'Feature', 'properties' => ['line' => 'tbm:A'], 'geometry' => ['type' => 'MultiLineString', 'coordinates' => [[[-0.58, 44.84], [-0.56, 44.84]]]]],
    ]]));
    CatalogImport::query()->create(['network' => 'tbm', 'etag' => 'v1', 'shapes_fingerprint' => 'f1', 'imported_at' => now()]);
    VehiclePosition::query()->create([
        'vehicle_id' => 'tbm:1234', 'network' => 'tbm', 'latitude' => 44.84, 'longitude' => -0.57,
        'line_id' => 'tbm:A', 'recorded_at' => now(), 'received_at' => now(),
    ]);

    visit('/')
        ->assertPresent('[data-map][data-shape-count="1"]')
        ->assertScript("performance.getEntriesByType('resource').find((entry) => entry.name.endsWith('/shapes')).startTime > performance.getEntriesByType('resource').find((entry) => entry.name.endsWith('/vehicles')).responseEnd", true)
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

it('lists vehicles by line in the text view and opens their popup from the keyboard', function (): void {
    Line::query()->create(['id' => 'tbm:A', 'network' => 'tbm', 'short_name' => 'A', 'long_name' => 'Le Haillan Rostand / Floirac Dravemont', 'mode' => TransportMode::Tram, 'color' => '81057F', 'sync_id' => Str::uuid()]);

    foreach (['1234' => 'tbm:A', 'lineless' => null, 'stranger' => 'tbm:gone'] as $vehicle => $line) {
        VehiclePosition::query()->create([
            'vehicle_id' => "tbm:{$vehicle}", 'network' => 'tbm', 'latitude' => 44.84, 'longitude' => -0.57,
            'line_id' => $line, 'recorded_at' => now()->subMinutes(3), 'received_at' => now(),
        ]);
    }

    visit('/')
        ->assertPresent('[data-map][data-vehicle-count="3"]')
        // A line the catalogue lacks shows like no line at all, in the last group.
        ->assertScript("[...document.querySelectorAll('#vue-texte h3')].map((heading) => heading.textContent).join(' | ')", 'Tram A – Le Haillan Rostand / Floirac Dravemont | Ligne inconnue')
        ->assertSeeIn('[data-vehicle="tbm:stranger"]', 'il y a 3 min')
        // Tab skips the headings: each button's accessible name, as computed for assistive technologies, says its line.
        // Chromium puts a space before the visually hidden text, which is positioned: the pattern allows it.
        ->assertPresent('[data-vehicle="tbm:1234"] >> internal:role=button[name=/^Véhicule 1234 ?, Tram A, afficher sur la carte$/]')
        ->assertPresent('[data-vehicle="tbm:stranger"] >> internal:role=button[name=/^Véhicule stranger ?, ligne inconnue, afficher sur la carte$/]')
        ->keys('[data-vehicle="tbm:1234"] button', 'Enter')
        ->assertSeeIn('[role="dialog"][aria-label="Véhicule 1234"]', 'A – Le Haillan Rostand / Floirac Dravemont')
        // The popup takes the focus, so that keyboard and screen reader users land on it.
        ->assertScript("document.activeElement.closest('[role=\"dialog\"]') !== null", true)
        ->assertNoAccessibilityIssues(level: 3)
        ->keys('[role="dialog"] button', 'Escape')
        ->assertMissing('[role="dialog"]')
        ->assertScript("document.activeElement.closest('[data-vehicle]')?.dataset.vehicle", 'tbm:1234')
        ->assertNoJavaScriptErrors();
});

it('keeps the text view in a side panel on a portrait screen', function (): void {
    Line::query()->create(['id' => 'tbm:A', 'network' => 'tbm', 'short_name' => 'A', 'mode' => TransportMode::Tram, 'color' => '81057F', 'sync_id' => Str::uuid()]);
    VehiclePosition::query()->create([
        'vehicle_id' => 'tbm:1234', 'network' => 'tbm', 'latitude' => 44.84, 'longitude' => -0.57,
        'line_id' => 'tbm:A', 'recorded_at' => now(), 'received_at' => now(),
    ]);

    visit('/')
        ->on()->mobile()
        ->assertPresent('[data-map][data-vehicle-count="1"]')
        ->assertMissing('#vue-texte')
        ->assertAttribute('button[aria-controls="vue-texte"]', 'aria-expanded', 'false')
        ->click('button[aria-controls="vue-texte"]')
        ->assertVisible('#vue-texte')
        ->assertAttribute('button[aria-controls="vue-texte"]', 'aria-expanded', 'true')
        ->assertScript('document.activeElement.id', 'vue-texte')
        ->assertNoAccessibilityIssues(level: 3)
        // Choosing a vehicle closes the panel, which would hide the popup.
        ->keys('[data-vehicle="tbm:1234"] button', 'Enter')
        ->assertPresent('[role="dialog"][aria-label="Véhicule 1234"]')
        ->assertMissing('#vue-texte')
        // Escape on the popup reopens the panel on that vehicle; Escape again closes it, back to its button.
        ->keys('[role="dialog"] button', 'Escape')
        ->assertVisible('#vue-texte')
        ->assertScript("document.activeElement.closest('[data-vehicle]')?.dataset.vehicle", 'tbm:1234')
        ->keys('[data-vehicle="tbm:1234"] button', 'Escape')
        ->assertMissing('#vue-texte')
        ->assertScript("document.activeElement.getAttribute('aria-controls')", 'vue-texte')
        ->assertNoJavaScriptErrors();
});

it('pauses and resumes live updates without a request while paused', function (): void {
    config(['map.refresh_every_seconds' => 1]);
    VehiclePosition::query()->create([
        'vehicle_id' => 'tbm:1234', 'network' => 'tbm', 'latitude' => 44.84, 'longitude' => -0.57,
        'line_id' => null, 'recorded_at' => now(), 'received_at' => now(),
    ]);
    // Requests started after the pause: one already on its way when the button is pressed may end after it.
    $requestsSincePause = "performance.getEntriesByType('resource').filter((entry) => entry.name.includes('/vehicles') && entry.startTime > window.pausedAt).length";

    $page = visit('/')
        ->assertPresent('[data-map][data-vehicle-count="1"]')
        ->assertAttribute('[data-pause]', 'aria-pressed', 'false')
        ->click('[data-pause]')
        ->assertAttribute('[data-pause]', 'aria-pressed', 'true')
        ->assertSeeIn('[data-status]', 'Mises à jour en pause, dernière à')
        ->assertNoAccessibilityIssues(level: 3);
    $page->script('window.pausedAt = performance.now()');

    // Two intervals without a request, then one request as soon as it resumes.
    $page->wait(2.5)
        ->assertScript($requestsSincePause, 0)
        ->click('[data-pause]')
        ->assertAttribute('[data-pause]', 'aria-pressed', 'false')
        ->wait(0.5)
        ->assertScript($requestsSincePause, 1)
        ->assertSeeIn('[data-status]', 'Mis à jour à')
        ->assertNoJavaScriptErrors();
});
