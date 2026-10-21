<?php

declare(strict_types=1);

use App\Actions\RecordPositions;
use App\Enums\TransportMode;
use App\Ingestion\VehiclePosition as IngestionPosition;
use App\Models\Line;
use App\Models\VehiclePosition;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

it('renames the open popup when a poll changes the destination of its vehicle', function (): void {
    config(['map.refresh_every_seconds' => 1]);
    Line::query()->create(['id' => 'tbm:A', 'network' => 'tbm', 'short_name' => 'A', 'mode' => TransportMode::Tram, 'color' => '81057F', 'sync_id' => Str::uuid()]);
    $record = fn (string $destination) => app(RecordPositions::class)->handle('tbm', [
        new IngestionPosition('tbm:1234', 44.84, -0.57, now()->toDateTimeImmutable(), 'tbm:A', destination: $destination),
    ], CarbonImmutable::now());
    $record('La Gardette');

    $page = visit('/')
        ->assertPresent('[data-map][data-vehicle-count="1"]')
        ->keys('[data-vehicle="tbm:1234"] button', 'Enter')
        ->assertPresent('[role="dialog"][aria-label="Tram A - La Gardette, véhicule 1234"]');

    $record('Pin Galant');

    $page->wait(2)
        ->assertPresent('[role="dialog"][aria-label="Tram A - Pin Galant, véhicule 1234"]')
        ->assertSeeIn('[role="dialog"]', 'Pin Galant')
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
