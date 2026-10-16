<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Transit_realtime\FeedEntity;
use Transit_realtime\FeedHeader;
use Transit_realtime\FeedMessage;
use Transit_realtime\Position;
use Transit_realtime\TripDescriptor;
use Transit_realtime\VehicleDescriptor;
use Transit_realtime\VehiclePosition as GtfsVehiclePosition;

pest()->extend(TestCase::class)->in('Feature');
pest()->use(RefreshDatabase::class)->in('Feature');
pest()->extend(TestCase::class)->use(RefreshDatabase::class)->in('Browser');

// The basemap comes from OpenFreeMap over the network: allow it more than the default 5 s.
pest()->browser()->timeout(20_000);

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

/**
 * @param  array<string, mixed>  $vehicle
 */
function feedEntity(string $id, array $vehicle): FeedEntity
{
    $position = new GtfsVehiclePosition;

    if (isset($vehicle['lat'])) {
        $position->setPosition((new Position)->setLatitude($vehicle['lat'])->setLongitude($vehicle['lon']));
    }
    if (isset($vehicle['id']) || isset($vehicle['label'])) {
        $position->setVehicle((new VehicleDescriptor)->setId($vehicle['id'] ?? '')->setLabel($vehicle['label'] ?? ''));
    }
    if (isset($vehicle['route']) || isset($vehicle['trip'])) {
        $position->setTrip((new TripDescriptor)->setRouteId($vehicle['route'] ?? '')->setTripId($vehicle['trip'] ?? ''));
    }
    if (isset($vehicle['timestamp'])) {
        $position->setTimestamp($vehicle['timestamp']);
    }

    return (new FeedEntity)->setId($id)->setVehicle($position);
}

/**
 * @param  list<FeedEntity>  $entities
 */
function feedPayload(array $entities, int $headerTimestamp = 1792145353): string
{
    return (new FeedMessage)
        ->setHeader((new FeedHeader)->setGtfsRealtimeVersion('2.0')->setTimestamp($headerTimestamp))
        ->setEntity($entities)
        ->serializeToString();
}
