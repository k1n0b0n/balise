<?php

declare(strict_types=1);

use App\Enums\TransportMode;
use App\Livewire\Home;
use App\Models\Line;
use App\Models\VehiclePosition;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

it('renders the home page as a Livewire component', function (): void {
    $this->get('/')
        ->assertOk()
        ->assertSeeLivewire(Home::class);
});

it('renders the text view on the server, readable before JavaScript loads', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-10-16 12:00:00'));

    foreach ([['tbm:B', 'B', TransportMode::Tram, null], ['tbm:10', '10', TransportMode::Bus, 'Gare Saint-Jean / Mérignac'], ['tbm:9', '9', TransportMode::Bus, null]] as [$id, $name, $mode, $longName]) {
        Line::query()->create(['id' => $id, 'network' => 'tbm', 'short_name' => $name, 'long_name' => $longName, 'mode' => $mode, 'sync_id' => Str::uuid()]);
    }

    foreach (['20' => 'tbm:10', '3' => 'tbm:10', 'tram' => 'tbm:B', 'nine' => 'tbm:9', 'lineless' => null, 'tbm:odd' => 'tbm:gone'] as $vehicle => $line) {
        VehiclePosition::query()->create([
            'vehicle_id' => "tbm:{$vehicle}", 'network' => 'tbm', 'latitude' => 44.84, 'longitude' => -0.57,
            'line_id' => $line, 'recorded_at' => now()->subSeconds(25), 'received_at' => now(),
        ]);
    }

    $this->get('/')
        ->assertOk()
        // Natural order of the short names, digits before letters; vehicles of unknown lines last, in one group.
        ->assertSeeInOrder(['Bus 9', 'Bus 10 – Gare Saint-Jean / Mérignac', 'Véhicule 3', 'Véhicule 20', 'Tram B', 'Ligne inconnue', 'Véhicule lineless', 'Véhicule tbm:odd'])
        // Times in the network's timezone: without JavaScript nothing updates, so an age would turn false.
        ->assertSee('relevé à 13:59:35')
        ->assertSee('Mis à jour à 14:00');
});

it('says so when no vehicle runs', function (): void {
    $this->get('/')
        ->assertOk()
        ->assertSee('En attente des positions')
        ->assertSee('Aucun véhicule en circulation.');
});

it('lists the line in the URL first, before JavaScript loads, and keeps the others', function (): void {
    foreach (['A' => TransportMode::Tram, '10' => TransportMode::Bus] as $name => $mode) {
        Line::query()->create(['id' => "tbm:{$name}", 'network' => 'tbm', 'short_name' => $name, 'mode' => $mode, 'sync_id' => Str::uuid()]);
        VehiclePosition::query()->create([
            'vehicle_id' => "tbm:on-{$name}", 'network' => 'tbm', 'latitude' => 44.84, 'longitude' => -0.57,
            'line_id' => "tbm:{$name}", 'recorded_at' => now(), 'received_at' => now(),
        ]);
    }

    // Digits before letters, unless a line is selected.
    $this->get('/')->assertSeeInOrder(['Véhicule on-10', 'Véhicule on-A']);

    $this->get('/?ligne=A')
        ->assertOk()
        ->assertSee('<option value="A" selected>', escape: false)
        ->assertSee('Désélectionner la ligne <span data-line-clear-name>A</span>', escape: false)
        ->assertSeeInOrder(['Véhicule on-A', 'Véhicule on-10']);

    // Trams first in the selector, whatever the names.
    $this->get('/')->assertSeeInOrder(['<optgroup label="Tram">', '<optgroup label="Bus">'], escape: false);

    // A line the network does not have changes nothing.
    $this->get('/?ligne=nowhere')->assertSeeInOrder(['Véhicule on-10', 'Véhicule on-A']);
});

it('lists in the selector only the lines that run, and the selected one in any case', function (): void {
    foreach (['A', 'B', 'C'] as $name) {
        Line::query()->create(['id' => "tbm:{$name}", 'network' => 'tbm', 'short_name' => $name, 'mode' => TransportMode::Tram, 'sync_id' => Str::uuid()]);
    }
    VehiclePosition::query()->create([
        'vehicle_id' => 'tbm:1', 'network' => 'tbm', 'latitude' => 44.84, 'longitude' => -0.57,
        'line_id' => 'tbm:A', 'recorded_at' => now(), 'received_at' => now(),
    ]);

    $this->get('/')
        ->assertSee('<option value="A"', escape: false)
        ->assertDontSee('<option value="B"', escape: false)
        ->assertDontSee('<option value="C"', escape: false);

    // A shared link to a line without vehicles still shows it selected.
    $this->get('/?ligne=C')
        ->assertSee('<option value="C" selected>', escape: false)
        ->assertDontSee('<option value="B"', escape: false);
});

it('groups a line by destination, sorted by name ignoring case, the unknown one last (E3.7)', function (): void {
    Line::query()->create(['id' => 'tbm:A', 'network' => 'tbm', 'short_name' => 'A', 'mode' => TransportMode::Tram, 'sync_id' => Str::uuid()]);

    foreach (['2231' => 'Pin Galant', '2230' => 'LA GARDETTE', '2229' => 'Pin Galant', '2228' => null] as $vehicle => $destination) {
        VehiclePosition::query()->create([
            'vehicle_id' => "tbm:{$vehicle}", 'network' => 'tbm', 'latitude' => 44.84, 'longitude' => -0.57,
            'line_id' => 'tbm:A', 'destination' => $destination, 'recorded_at' => now(), 'received_at' => now(),
        ]);
    }

    $this->get('/')
        ->assertOk()
        ->assertSeeInOrder([
            '<h3', 'Tram A', '</h3>',
            '<h4', 'LA GARDETTE', '</h4>', 'Tram A - LA GARDETTE, véhicule 2230',
            '<h4', 'Pin Galant', '</h4>', 'Tram A - Pin Galant, véhicule 2229', 'Tram A - Pin Galant, véhicule 2231',
            '<h4', 'Destination inconnue', '</h4>', 'Véhicule 2228',
        ], escape: false);
});
