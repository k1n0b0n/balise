<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Transport networks
|--------------------------------------------------------------------------
|
| One entry per network, keyed by the prefix of its ids (ADR-0001). Each
| network picks a driver per port; adding a network on a supported
| standard only needs a new entry here.
|
*/

return [

    'tbm' => [
        'name' => 'TBM',
        'timezone' => 'Europe/Paris',

        'catalog' => [
            'driver' => 'gtfs',
            // Public open data key, published on transport.data.gouv.fr.
            'url' => env('TBM_GTFS_URL', 'https://bdx.mecatran.com/utw/ws/gtfsfeed/static/bordeaux?apiKey=opendata-bordeaux-metropole-flux-gtfs-rt'),
        ],

        'vehicles' => [
            'driver' => 'gtfs-rt',
            'url' => env('TBM_GTFS_RT_URL', 'https://bdx.mecatran.com/utw/ws/gtfsfeed/vehicles/bordeaux?apiKey=opendata-bordeaux-metropole-flux-gtfs-rt'),
            // The feed changes every 20 s (measured 2026-10-16); must divide 60.
            'poll_every_seconds' => 20,
            // Freshness limits relative to reception (ADR-0013).
            'max_age_seconds' => 300,
            'max_ahead_seconds' => 120,
        ],
    ],

];
