<?php

declare(strict_types=1);

use App\Enums\TransportMode;
use App\Models\Line;
use App\Models\Stop;
use App\Models\VehiclePosition;
use Illuminate\Support\Str;

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
        ->assertSeeIn('[role="dialog"][aria-label="Véhicule 1234"]', 'A (Le Haillan Rostand / Floirac Dravemont)')
        // Minutes, not seconds: the age stays the same however slow the browser is to open the popup.
        ->assertSeeIn('[role="dialog"]', 'il y a 3 min')
        ->assertNoAccessibilityIssues(level: 3)
        ->keys('[role="dialog"] button', 'Escape')
        ->assertMissing('[role="dialog"]')
        // The click had focused the canvas; Escape gives the focus back to it.
        ->assertScript("document.activeElement === document.querySelector('[data-map] canvas')", true)
        ->assertNoJavaScriptErrors();
});

it('goes through stacked vehicles with the arrows of the popup', function (): void {
    Line::query()->create(['id' => 'tbm:A', 'network' => 'tbm', 'short_name' => 'A', 'mode' => TransportMode::Tram, 'color' => '81057F', 'sync_id' => Str::uuid()]);

    foreach (['1111', '2222'] as $vehicle) {
        VehiclePosition::query()->create([
            'vehicle_id' => "tbm:{$vehicle}", 'network' => 'tbm', 'latitude' => 44.84, 'longitude' => -0.57,
            'line_id' => 'tbm:A', 'recorded_at' => now(), 'received_at' => now(),
        ]);
    }

    // Two stops around the vehicles: the map fits them, so the vehicles sit at the centre of the canvas.
    foreach (['west' => [44.83, -0.58], 'east' => [44.85, -0.56]] as $id => [$latitude, $longitude]) {
        Stop::query()->create(['id' => "tbm:{$id}", 'network' => 'tbm', 'name' => $id, 'latitude' => $latitude, 'longitude' => $longitude, 'sync_id' => Str::uuid()]);
    }

    visit('/')
        ->assertPresent('[data-map][data-vehicle-count="2"]')
        ->click('[data-map] canvas')
        ->assertPresent('[role="dialog"][aria-label="Véhicule 1111"]')
        ->assertSeeIn('[role="dialog"]', '1 sur 2 à cet endroit')
        ->assertNoAccessibilityIssues(level: 3)
        ->keys('[data-step="1"]', 'Enter')
        ->assertPresent('[role="dialog"][aria-label="Véhicule 2222"]')
        ->assertSeeIn('[role="dialog"]', '2 sur 2 à cet endroit')
        // The arrow keeps the focus, so that the next press goes on.
        ->assertScript('document.activeElement.dataset.step', '1')
        ->assertScript('location.search', '?ligne=A&vehicule=2222')
        ->keys('[data-step="1"]', 'Enter')
        ->assertPresent('[role="dialog"][aria-label="Véhicule 1111"]')
        ->keys('[data-step="-1"]', 'Enter')
        ->assertPresent('[role="dialog"][aria-label="Véhicule 2222"]')
        ->assertNoJavaScriptErrors();
});
