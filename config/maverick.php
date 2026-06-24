<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Use sudo to manage maverick
    |--------------------------------------------------------------------------
    |
    | On the Raspberry Pi, PHP-FPM runs as www-data and GPIO access requires
    | root. Enable this and configure sudoers for maverick.sh start/stop.
    |
    */

    'use_sudo' => env('MAVERICK_USE_SUDO', false),

    'binary' => env('MAVERICK_BINARY', base_path('maverick')),

    'script' => env('MAVERICK_SCRIPT', base_path('maverick.sh')),

];
