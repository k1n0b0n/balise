<?php

declare(strict_types=1);

return [

    // Vector basemap without key or account (ADR-0011).
    'style' => env('MAP_STYLE_URL', 'https://tiles.openfreemap.org/styles/positron'),

];
