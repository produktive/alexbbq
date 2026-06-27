<?php

$defaultScript = env('MAVERICK_SCRIPT') ?? (
    env('APP_ENV') === 'local' && is_file(base_path('maverick-fake.sh'))
        ? base_path('maverick-fake.sh')
        : base_path('maverick.sh')
);

return [

    /*
    |--------------------------------------------------------------------------
    | Maverick daemon
    |--------------------------------------------------------------------------
    |
    | The maverick binary reads temperature probes on the Raspberry Pi. PHP-FPM
    | runs as www-data, so start/stop always goes through maverick.sh with sudo.
    |
    | Local development can use maverick-fake.sh instead, which runs
    | `php artisan maverick:simulate` to generate realistic probe readings.
    |
    */

    'binary' => env('MAVERICK_BINARY', base_path('maverick')),

    'script' => $defaultScript,

    'use_sudo' => filter_var(
        env('MAVERICK_USE_SUDO', ! str_contains($defaultScript, 'maverick-fake')),
        FILTER_VALIDATE_BOOL,
    ),

    'fake' => [
        'interval' => (int) env('MAVERICK_FAKE_INTERVAL', 12),
        'bbq_target' => (int) env('MAVERICK_FAKE_BBQ_TARGET', 225),
        'pid_file' => storage_path('maverick-fake.pid'),
    ],

    'log_file' => str_contains($defaultScript, 'maverick-fake')
        ? storage_path('logs/maverick-fake.log')
        : storage_path('logs/maverick.log'),

    'php_binary' => env('MAVERICK_PHP'),

];
