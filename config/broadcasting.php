<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Broadcaster
    |--------------------------------------------------------------------------
    |
    | This option controls the default broadcaster that will be used by the
    | framework when an event needs to be broadcast. You may set this to
    | any of the connections defined in the "connections" array below.
    |
    | Supported: "reverb", "pusher", "ably", "redis", "log", "null"
    |
    */

    'default' => env('BROADCAST_CONNECTION', 'null'),

    /*
    |--------------------------------------------------------------------------
    | Broadcast Connections
    |--------------------------------------------------------------------------
    |
    | Here you may define all of the broadcast connections that will be used
    | to broadcast events to other systems or over WebSockets. Samples of
    | each available type of connection are provided inside this array.
    |
    */

    'connections' => [

        'reverb' => [
            'driver' => 'reverb',
            'key' => env('REVERB_APP_KEY'),
            'secret' => env('REVERB_APP_SECRET'),
            'app_id' => env('REVERB_APP_ID'),
            'options' => [
                // Server-side HTTP API. Production defaults to local Reverb; Herd local inherits REVERB_*.
                'host' => env('REVERB_SERVER_HOST') ?: (env('APP_ENV') === 'local' ? env('REVERB_HOST', '127.0.0.1') : '127.0.0.1'),
                'port' => (int) (env('REVERB_SERVER_PORT') ?: (env('APP_ENV') === 'local' ? env('REVERB_PORT', 8080) : 8080)),
                'scheme' => env('REVERB_SERVER_SCHEME') ?: (env('APP_ENV') === 'local' ? env('REVERB_SCHEME', 'http') : 'http'),
                'useTLS' => (env('REVERB_SERVER_SCHEME') ?: (env('APP_ENV') === 'local' ? env('REVERB_SCHEME', 'http') : 'http')) === 'https',
            ],
            // Browser client (Echo) — baked into config:cache; read via config() in Blade.
            'client' => [
                'host' => env('REVERB_HOST'),
                'port' => (int) env('REVERB_PORT', 443),
                'scheme' => env('REVERB_SCHEME', 'https'),
            ],
            'client_options' => [
                // Herd uses a self-signed cert; PHP CLI cannot verify it unless REVERB_VERIFY_SSL=true.
                'verify' => filter_var(env('REVERB_VERIFY_SSL', env('APP_ENV') !== 'local'), FILTER_VALIDATE_BOOL),
            ],
        ],

        'pusher' => [
            'driver' => 'pusher',
            'key' => env('PUSHER_APP_KEY'),
            'secret' => env('PUSHER_APP_SECRET'),
            'app_id' => env('PUSHER_APP_ID'),
            'options' => [
                'cluster' => env('PUSHER_APP_CLUSTER'),
                'host' => env('PUSHER_HOST') ?: 'api-'.env('PUSHER_APP_CLUSTER', 'mt1').'.pusher.com',
                'port' => env('PUSHER_PORT', 443),
                'scheme' => env('PUSHER_SCHEME', 'https'),
                'encrypted' => true,
                'useTLS' => env('PUSHER_SCHEME', 'https') === 'https',
            ],
            'client_options' => [
                // Guzzle client options: https://docs.guzzlephp.org/en/stable/request-options.html
            ],
        ],

        'ably' => [
            'driver' => 'ably',
            'key' => env('ABLY_KEY'),
        ],

        'log' => [
            'driver' => 'log',
        ],

        'null' => [
            'driver' => 'null',
        ],

    ],

];
