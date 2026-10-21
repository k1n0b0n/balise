<?php

declare(strict_types=1);

use App\Enums\TransportMode;
use App\Models\CatalogImport;
use App\Models\Line;
use App\Models\VehiclePosition;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

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

it('lists vehicles by line and destination in the text view and opens their popup from the keyboard', function (): void {
    Line::query()->create(['id' => 'tbm:A', 'network' => 'tbm', 'short_name' => 'A', 'long_name' => 'Le Haillan Rostand / Floirac Dravemont', 'mode' => TransportMode::Tram, 'color' => '81057F', 'sync_id' => Str::uuid()]);

    foreach (['1234' => ['tbm:A', 'La Gardette'], 'nameless' => ['tbm:A', null], 'lineless' => [null, null], 'stranger' => ['tbm:gone', 'CENON GARE']] as $vehicle => [$line, $destination]) {
        VehiclePosition::query()->create([
            'vehicle_id' => "tbm:{$vehicle}", 'network' => 'tbm', 'latitude' => 44.84, 'longitude' => -0.57,
            'line_id' => $line, 'destination' => $destination, 'recorded_at' => now()->subMinutes(3), 'received_at' => now(),
        ]);
    }

    visit('/')
        ->assertPresent('[data-map][data-vehicle-count="4"]')
        // A line the catalogue lacks shows like no line at all, in the last group.
        ->assertScript("[...document.querySelectorAll('#vue-texte h3')].map((heading) => heading.textContent).join(' | ')", 'Tram A - Le Haillan Rostand / Floirac Dravemont | Ligne inconnue')
        ->assertScript("[...document.querySelectorAll('#vue-texte h4')].map((heading) => heading.textContent).join(' | ')", 'La Gardette | Destination inconnue | CENON GARE | Destination inconnue')
        ->assertSeeIn('[data-vehicle="tbm:stranger"]', 'il y a 3 min')
        // Tab skips the headings: each button's accessible name, as computed for assistive technologies, says its line,
        // and its number when other vehicles share its name. Chromium puts a space before the visually hidden text,
        // which is positioned: the pattern allows it.
        ->assertPresent('[data-vehicle="tbm:1234"] >> internal:role=button[name=/^Tram A - La Gardette ?, véhicule 1234, afficher sur la carte$/]')
        ->assertPresent('[data-vehicle="tbm:nameless"] >> internal:role=button[name=/^Véhicule nameless ?, Tram A, afficher sur la carte$/]')
        ->assertPresent('[data-vehicle="tbm:stranger"] >> internal:role=button[name=/^Véhicule stranger ?, ligne inconnue, afficher sur la carte$/]')
        ->keys('[data-vehicle="tbm:1234"] button', 'Enter')
        ->assertSeeIn('[role="dialog"][aria-label="Tram A - La Gardette, véhicule 1234"]', 'A - Le Haillan Rostand / Floirac Dravemont')
        ->assertSeeIn('[role="dialog"]', 'La Gardette')
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
