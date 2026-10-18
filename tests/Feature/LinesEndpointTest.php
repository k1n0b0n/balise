<?php

declare(strict_types=1);

use App\Enums\TransportMode;
use App\Models\CatalogImport;
use App\Models\Line;
use Illuminate\Support\Str;

beforeEach(function (): void {
    Line::query()->create(['id' => 'tbm:A', 'network' => 'tbm', 'short_name' => 'A', 'long_name' => 'Le Haillan Rostand / Floirac Dravemont', 'mode' => TransportMode::Tram, 'color' => '81057f', 'sync_id' => Str::uuid()]);
    Line::query()->create(['id' => 'tbm:12', 'network' => 'tbm', 'short_name' => '12', 'mode' => TransportMode::Bus, 'color' => null, 'sync_id' => Str::uuid()]);
    CatalogImport::query()->create(['network' => 'tbm', 'etag' => 'v1', 'imported_at' => now()]);
});

it('returns what each line looks like on the map and in the text view', function (): void {
    $this->getJson(route('lines', 'tbm'))
        ->assertOk()
        ->assertExactJson(['lines' => [
            ['id' => 'tbm:12', 'shortName' => '12', 'longName' => null, 'mode' => 'bus', 'modeName' => 'Bus', 'colour' => Line::NEUTRAL_COLOUR],
            ['id' => 'tbm:A', 'shortName' => 'A', 'longName' => 'Le Haillan Rostand / Floirac Dravemont', 'mode' => 'tram', 'modeName' => 'Tram', 'colour' => '#81057F'],
        ]]);
});

it('answers 304 until the next catalogue import', function (): void {
    $etag = $this->getJson(route('lines', 'tbm'))->assertOk()->headers->get('ETag');

    $this->getJson(route('lines', 'tbm'), ['If-None-Match' => $etag])->assertNotModified()->assertContent('');

    $this->travel(1)->days();
    CatalogImport::query()->whereKey('tbm')->update(['imported_at' => now()]);

    $this->getJson(route('lines', 'tbm'), ['If-None-Match' => $etag])->assertOk();
});

it('does not know other networks', function (): void {
    $this->getJson(route('lines', 'nowhere'))->assertNotFound();
});
