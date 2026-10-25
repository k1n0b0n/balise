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
        // Written in the README of each recording (E5.1): the licence asks for the source.
        'attribution' => 'Bordeaux Métropole (TBM), through transport.data.gouv.fr, under the Licence Ouverte 2.0 (Etalab)',

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
            // TBM puts the destination sign in `vehicle.label`; elsewhere it may be a fleet number (ADR-0018).
            'label_is_destination' => true,
        ],

        'recordings' => [
            'enabled' => (bool) env('RECORDINGS_ENABLED', false),
            // TBM empties between about 01:30 and 04:30; a night it does not (New Year's Eve) is cut here.
            'service_day_cut_at' => '04:00',
        ],
    ],

];
