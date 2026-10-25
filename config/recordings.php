<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Feed recordings
|--------------------------------------------------------------------------
|
| Vehicle feeds recorded on the server, one archive per service day (E5.1,
| ADR-0022). Each network turns them on in config/networks.php.
|
*/

return [

    // When the finished days are archived, in each network's time zone: set per server, outside its maintenance.
    'archive_at' => env('RECORDINGS_ARCHIVE_AT', '05:15'),

    // A service day ends after this long without a fresh vehicle.
    'service_gap_minutes' => 30,

    // Archives kept per network: about 86 MB each for TBM.
    'keep' => 30,

];
