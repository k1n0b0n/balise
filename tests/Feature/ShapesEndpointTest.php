<?php

declare(strict_types=1);

use App\Models\CatalogImport;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('local');
});

it('serves the network shapes written by the import', function (): void {
    Storage::disk('local')->put('shapes/tbm.json', '{"type":"FeatureCollection","features":[]}');
    CatalogImport::query()->create(['network' => 'tbm', 'etag' => 'v1', 'shapes_fingerprint' => 'f1', 'imported_at' => now()]);

    $this->getJson(route('shapes', 'tbm'))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/json')
        ->assertHeader('ETag', '"f1"')
        ->assertExactJson(['type' => 'FeatureCollection', 'features' => []]);
});

it('answers 304 until the shapes change', function (): void {
    Storage::disk('local')->put('shapes/tbm.json', '{"type":"FeatureCollection","features":[]}');
    CatalogImport::query()->create(['network' => 'tbm', 'etag' => 'v1', 'shapes_fingerprint' => 'f1', 'imported_at' => now()]);

    $this->getJson(route('shapes', 'tbm'), ['If-None-Match' => '"f1"'])->assertNotModified()->assertContent('');

    CatalogImport::query()->whereKey('tbm')->update(['shapes_fingerprint' => 'f2']);

    $this->getJson(route('shapes', 'tbm'), ['If-None-Match' => '"f1"'])->assertOk();
});

it('has nothing to serve before the first import or for other networks', function (): void {
    $this->getJson(route('shapes', 'tbm'))->assertNotFound();
    $this->getJson(route('shapes', 'nowhere'))->assertNotFound();
});
