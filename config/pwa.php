<?php

return [

    'description' => env(
        'PWA_DESCRIPTION',
        'Live BBQ temperature monitoring for the Maverick ET-732.',
    ),

    'theme_color' => env('PWA_THEME_COLOR', '#1f1f1f'),

    'background_color' => env('PWA_BACKGROUND_COLOR', '#1f1f1f'),

    'status_bar_style' => env('PWA_STATUS_BAR_STYLE', 'black-translucent'),

    'short_name' => env('PWA_SHORT_NAME'),

    'start_url' => env('PWA_START_URL', '/'),

    'lang' => env('PWA_LANG'),

    'dir' => env('PWA_DIR', 'ltr'),

    'orientation' => env('PWA_ORIENTATION', 'any'),

    'prefer_related_applications' => env('PWA_PREFER_RELATED_APPLICATIONS', false),

    'categories' => [
        'utilities',
        'food',
    ],

    'shortcuts' => [
        ['name' => 'Home', 'short_name' => 'Home', 'route' => 'home'],
        ['name' => 'Cooks', 'short_name' => 'Cooks', 'route' => 'cooks'],
        ['name' => 'Stats', 'short_name' => 'Stats', 'route' => 'stats'],
    ],

];
