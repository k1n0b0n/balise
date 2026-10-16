<?php

declare(strict_types=1);

return [

    // Vector basemap without key or account (ADR-0011).
    'style' => env('MAP_STYLE_URL', 'https://tiles.openfreemap.org/styles/positron'),

    // How often the page asks for changes. Feeds change every 20 s at best, so most answers are a 304 without a body.
    'refresh_every_seconds' => 5,

];
