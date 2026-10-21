<?php

declare(strict_types=1);

use App\Enums\TransportMode;
use App\Models\Line;
use App\Models\VehiclePosition;
use Illuminate\Support\Str;

it('highlights the selected line, without reloading or hiding the others, and keeps it in the URL', function (): void {
    foreach (['A' => '81057F', 'B' => 'DA0063'] as $line => $colour) {
        Line::query()->create(['id' => "tbm:{$line}", 'network' => 'tbm', 'short_name' => $line, 'mode' => TransportMode::Tram, 'color' => $colour, 'sync_id' => Str::uuid()]);
    }

    foreach (['1' => 'tbm:A', '2' => 'tbm:A', '3' => 'tbm:B'] as $vehicle => $line) {
        VehiclePosition::query()->create([
            'vehicle_id' => "tbm:{$vehicle}", 'network' => 'tbm', 'latitude' => 44.84, 'longitude' => -0.57,
            'line_id' => $line, 'recorded_at' => now(), 'received_at' => now(),
        ]);
    }

    $page = visit('/')
        ->assertPresent('[data-map][data-vehicle-count="3"]');
    $page->script('window.samePage = true');

    // Without a submit button: a choice applies at once.
    $page->assertMissing('[data-line-submit]')
        ->select('#ligne', 'B')
        ->assertScript('location.search', '?ligne=B')
        ->assertScript('window.samePage', true)
        ->assertScript("document.querySelector('[data-map]').dataset.highlightedLine", 'tbm:B')
        ->assertPresent('[data-map][data-vehicle-count="3"]')
        // Its group comes first in the text view, the others stay.
        ->assertScript("[...document.querySelectorAll('#vue-texte [data-vehicle]')].map((row) => row.dataset.vehicle).join()", 'tbm:3,tbm:1,tbm:2')
        ->assertNoAccessibilityIssues(level: 3)
        // A vehicle of another line selects that line.
        ->keys('[data-vehicle="tbm:1"] button', 'Enter')
        ->assertScript('location.search', '?ligne=A&vehicule=1')
        ->assertScript("document.querySelector('#ligne').value", 'A')
        ->assertScript("document.querySelector('[data-map]').dataset.highlightedLine", 'tbm:A')
        // Choosing another line ends the selection of a vehicle that is not on it.
        ->select('#ligne', 'B')
        ->assertScript('location.search', '?ligne=B')
        ->assertMissing('[role="dialog"]')
        ->assertPresent('[data-map][data-followed=""]')
        // The button next to the selector deselects the line; the focus goes back to the selector.
        // The cross is hidden from assistive technologies: the link's name says what it does.
        ->assertPresent('internal:role=link[name=/^ ?Désélectionner la ligne B$/]')
        ->click('[data-line-clear]')
        ->assertScript('location.search', '')
        ->assertMissing('[data-line-clear]')
        ->assertScript('document.activeElement.id', 'ligne')
        ->assertScript("document.querySelector('#ligne').value", '')
        ->select('#ligne', 'B')
        ->select('#ligne', '')
        ->assertScript('location.search', '')
        ->assertScript("document.querySelector('[data-map]').dataset.highlightedLine", '')
        ->assertScript("[...document.querySelectorAll('#vue-texte [data-vehicle]')].map((row) => row.dataset.vehicle).join()", 'tbm:1,tbm:2,tbm:3')
        ->assertNoJavaScriptErrors();
});

it('follows the chosen vehicle until the map is moved by hand, and reopens it from the URL', function (): void {
    Line::query()->create(['id' => 'tbm:A', 'network' => 'tbm', 'short_name' => 'A', 'mode' => TransportMode::Tram, 'color' => '81057F', 'sync_id' => Str::uuid()]);
    VehiclePosition::query()->create([
        'vehicle_id' => 'tbm:1234', 'network' => 'tbm', 'latitude' => 44.84, 'longitude' => -0.57,
        'line_id' => 'tbm:A', 'recorded_at' => now(), 'received_at' => now(),
    ]);

    visit('/')
        ->assertPresent('[data-map][data-vehicle-count="1"]')
        ->keys('[data-vehicle="tbm:1234"] button', 'Enter')
        ->assertPresent('[data-map][data-followed="tbm:1234"]')
        // Choosing a vehicle selects its line.
        ->assertScript('location.search', '?ligne=A&vehicule=1234')
        ->assertScript("document.querySelector('#ligne').value", 'A')
        ->assertScript("document.querySelector('[data-map]').dataset.highlightedLine", 'tbm:A')
        ->keys('[data-map] canvas', 'ArrowRight')
        ->assertPresent('[data-map][data-followed=""]')
        // Still selected: the popup stays open and the URL keeps the vehicle.
        ->assertPresent('[role="dialog"][aria-label="Véhicule 1234"]')
        // Closing the popup ends the selection of the vehicle and of its line.
        ->keys('[role="dialog"] button', 'Escape')
        ->assertScript('location.search', '')
        ->assertScript("document.querySelector('#ligne').value", '')
        ->assertScript("document.querySelector('[data-map]').dataset.highlightedLine", '')
        ->assertNoJavaScriptErrors();

    visit('/?vehicule=1234')
        ->assertPresent('[role="dialog"][aria-label="Véhicule 1234"]')
        ->assertPresent('[data-map][data-followed="tbm:1234"]')
        ->assertNoJavaScriptErrors();
});
