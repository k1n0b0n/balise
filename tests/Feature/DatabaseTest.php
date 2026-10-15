<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;

it('runs the test suite against PostgreSQL', function (): void {
    expect(DB::connection()->getDriverName())->toBe('pgsql')
        ->and(DB::connection()->getDatabaseName())->toBe('testing')
        ->and(DB::scalar('select version()'))->toStartWith('PostgreSQL');
});
