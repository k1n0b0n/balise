<?php

declare(strict_types=1);

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
