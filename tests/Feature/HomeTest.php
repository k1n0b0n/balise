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
        ->assertSeeInOrder(['Bus 9', 'Bus 10 – Gare Saint-Jean / Mérignac', 'Véhicule 3,', 'Véhicule 20,', 'Tram B', 'Ligne inconnue', 'Véhicule lineless,', 'Véhicule tbm:odd,'])
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

it('filters the text view by the line in the URL, before JavaScript loads', function (): void {
    foreach (['A' => TransportMode::Tram, '10' => TransportMode::Bus] as $name => $mode) {
        Line::query()->create(['id' => "tbm:{$name}", 'network' => 'tbm', 'short_name' => $name, 'mode' => $mode, 'sync_id' => Str::uuid()]);
        VehiclePosition::query()->create([
            'vehicle_id' => "tbm:on-{$name}", 'network' => 'tbm', 'latitude' => 44.84, 'longitude' => -0.57,
            'line_id' => "tbm:{$name}", 'recorded_at' => now(), 'received_at' => now(),
        ]);
    }

    $this->get('/?ligne=10')
        ->assertOk()
        ->assertSee('<option value="10" selected>', escape: false)
        ->assertSee('Véhicule on-10,')
        ->assertDontSee('Véhicule on-A,');

    // Trams first in the selector, whatever the names.
    $this->get('/')->assertSeeInOrder(['<optgroup label="Tram">', '<optgroup label="Bus">'], escape: false);

    // A line the network does not have filters nothing.
    $this->get('/?ligne=nowhere')
        ->assertSee('Véhicule on-10,')
        ->assertSee('Véhicule on-A,');
});
