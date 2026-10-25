<?php

declare(strict_types=1);

use App\Actions\RecordPositions;
use App\Enums\TransportMode;
use App\Ingestion\VehiclePosition as IngestionPosition;
use App\Models\Line;
use App\Models\VehiclePosition;
use App\Support\FeedTiming;
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

it('keeps the line selector to the lines that run, without changing it under the focus', function (): void {
    config(['map.refresh_every_seconds' => 1]);
    foreach (['A' => TransportMode::Tram, 'B' => TransportMode::Tram, '5' => TransportMode::Bus] as $name => $mode) {
        Line::query()->create(['id' => "tbm:{$name}", 'network' => 'tbm', 'short_name' => (string) $name, 'mode' => $mode, 'sync_id' => Str::uuid()]);
    }
    $run = fn (string $line) => app(RecordPositions::class)->handle('tbm', [
        new IngestionPosition("tbm:on-{$line}", 44.84, -0.57, now()->toDateTimeImmutable(), "tbm:{$line}"),
    ], CarbonImmutable::now());
    $run('A');
    $options = "[...document.querySelectorAll('#ligne option')].map((option) => option.value).join()";
    $groups = "[...document.querySelectorAll('#ligne optgroup')].map((group) => group.label).join()";

    $page = visit('/')
        ->assertPresent('[data-map][data-vehicle-count="1"]')
        ->assertScript($options, ',A');

    // Lines that start running wait for the selector to lose the focus, then come in the server's order.
    $page->script("document.querySelector('#ligne').focus()");
    $run('5');
    $run('B');
    $page->assertPresent('[data-map][data-vehicle-count="3"]')
        ->assertScript($options, ',A');
    $page->script("document.querySelector('#ligne').blur()");
    $page->assertScript($options, ',A,B,5')
        ->assertScript($groups, 'Tram,Bus');

    // A line that stops is taken out, unless it is the selected one.
    $page->select('#ligne', 'B');
    $page->script("document.querySelector('#ligne').blur()");
    // They age out as on a real poll: too old, then left out of the next feed.
    VehiclePosition::query()->whereIn('vehicle_id', ['tbm:on-B', 'tbm:on-5'])->update(['recorded_at' => now()->subHour()]);
    $run('A');
    $page->assertPresent('[data-map][data-vehicle-count="1"]')
        ->assertScript($options, ',A,B')
        ->assertScript("document.querySelector('#ligne').value", 'B')
        ->assertNoAccessibilityIssues(level: 3)
        ->assertNoJavaScriptErrors();
});

it('waits for the version the server expects rather than asking at its usual interval (E4.1)', function (): void {
    config(['map.refresh_every_seconds' => 1]);
    VehiclePosition::query()->create([
        'vehicle_id' => 'tbm:1234', 'network' => 'tbm', 'latitude' => 44.84, 'longitude' => -0.57,
        'line_id' => null, 'recorded_at' => now(), 'received_at' => now(),
    ]);
    // Published 8 s ago: the next version is expected in about 14 s (publication, a second to serve, one to record),
    // far enough for a slow page load not to reach it.
    app(FeedTiming::class)->remember('tbm', CarbonImmutable::now()->subSeconds(8));
    $expectedAt = (float) app(FeedTiming::class)->nextVersionAt('tbm', CarbonImmutable::now());
    $requests = "performance.getEntriesByType('resource').filter((entry) => entry.name.includes('/vehicles')).length";

    $page = visit('/')->assertPresent('[data-map][data-vehicle-count="1"]');

    // Asking every second, the page would have asked about three more times by now.
    $page->wait(3)->assertScript($requests, 1);
    // Past the expected time and its random second, it has asked again.
    $page->wait(max(0, $expectedAt - microtime(true)) + 2)->assertScript("({$requests}) >= 2", true)->assertNoJavaScriptErrors();
});
