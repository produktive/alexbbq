<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Maverick daemon
    |--------------------------------------------------------------------------
    |
    | The maverick binary reads temperature probes on the Raspberry Pi. PHP-FPM
    | runs as www-data, so start/stop always goes through maverick.sh with sudo.
    |
    */

    'binary' => env('MAVERICK_BINARY', base_path('maverick')),

    'script' => env('MAVERICK_SCRIPT', base_path('maverick.sh')),

];
