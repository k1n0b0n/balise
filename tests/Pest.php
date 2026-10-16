<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)->in('Feature');
pest()->use(RefreshDatabase::class)->in('Feature');

/**
 * Zip the fixture GTFS in tests/fixtures/gtfs/{name}, with some files replaced or removed (null).
 *
 * @param  array<string, string|null>  $replace
 */
function gtfsArchive(string $name = 'minimal', array $replace = []): string
{
    $path = tempnam(sys_get_temp_dir(), 'gtfs-fixture');
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::OVERWRITE);

    foreach (glob(__DIR__."/fixtures/gtfs/{$name}/*.txt") ?: [] as $file) {
        $zip->addFromString(basename($file), (string) file_get_contents($file));
    }

    foreach ($replace as $file => $contents) {
        $contents === null ? $zip->deleteName($file) : $zip->addFromString($file, $contents);
    }

    $zip->close();
    $bytes = (string) file_get_contents($path);
    unlink($path);

    return $bytes;
}
