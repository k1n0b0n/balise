<?php

declare(strict_types=1);

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

it('runs the test suite against PostgreSQL', function (): void {
    expect(DB::connection()->getDriverName())->toBe('pgsql')
        ->and(DB::connection()->getDatabaseName())->toBe('testing')
        ->and(DB::scalar('select version()'))->toStartWith('PostgreSQL');
});

it('reads timestamps in UTC whatever the zone of the server', function (): void {
    $connect = function (array $overrides): Connection {
        Config::set('database.connections.probe', [...Config::array('database.connections.pgsql'), ...$overrides]);
        DB::purge('probe');

        return DB::connection('probe');
    };

    // The server's PostgreSQL takes the machine's zone at install: Europe/Paris on the local machine of E1.3b.
    // Outside the test's transaction, so that new sessions see it.
    $connect([])->statement("alter database testing set timezone to 'Europe/Paris'");

    try {
        expect($connect(['timezone' => null])->scalar("select current_setting('TimeZone')"))->toBe('Europe/Paris')
            ->and($connect([])->scalar("select current_setting('TimeZone')"))->toBe('UTC');
    } finally {
        $connect([])->statement('alter database testing reset timezone');
        DB::purge('probe');
    }
});
