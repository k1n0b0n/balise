<?php

declare(strict_types=1);

use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

arch('strict types everywhere')
    ->expect('App')
    ->toUseStrictTypes();

arch('no debugging leftovers')
    ->expect(['dd', 'dump', 'var_dump', 'ray'])
    ->not->toBeUsed();

// ADR-0001: only the ingestion layer knows about feed formats.
arch('feed formats stay inside ingestion')
    ->expect('App')
    ->not->toUse(['Google\Protobuf', 'Transit_realtime'])
    ->ignoring('App\Ingestion');

// ADR-0002: every write of a position goes through the single write path, which tells the listeners. Carbon's
// `createFrom…` builds a date, not a row.
it('writes positions only through RecordPositions', function (): void {
    $writers = collect(Finder::create()->files()->in(dirname(__DIR__, 2).'/app'))
        ->reject(fn (SplFileInfo $file): bool => $file->getFilename() === 'RecordPositions.php')
        ->filter(fn (SplFileInfo $file): bool => preg_match('/VehiclePosition|vehicle_positions/', $file->getContents()) === 1
            && preg_match('/->(upsert|insert\w*|update\w*|delete|save\w*|forceDelete|truncate|increment|decrement)\(|::(create(?!From)\w*|firstOrCreate|updateOrCreate|insert\w*|upsert|destroy|truncate)\(/', $file->getContents()) === 1)
        ->map(fn (SplFileInfo $file): string => $file->getRelativePathname())
        ->values()
        ->all();

    expect($writers)->toBe([]);
});

// ADR-0010: the map module is plain TypeScript, mounted by Livewire and Alpine but never importing them.
it('keeps Livewire and Alpine out of the map module', function (): void {
    $importers = collect(Finder::create()->files()->in(dirname(__DIR__, 2).'/resources/js/map'))
        // `from 'alpinejs'`, `import 'alpinejs'` and `import('alpinejs')`.
        ->filter(fn (SplFileInfo $file): bool => preg_match('/(?:\bimport\s*(?:\(\s*)?|from\s+)[\'"](livewire|alpinejs|@livewire|@alpinejs)(?:[\/\'"])/i', $file->getContents()) === 1)
        ->map(fn (SplFileInfo $file): string => $file->getFilename())
        ->values()
        ->all();

    expect($importers)->toBe([]);
});
