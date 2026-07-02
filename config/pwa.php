<?php

return [

    'description' => env(
        'PWA_DESCRIPTION',
        'Live BBQ temperature monitoring for the Maverick ET-732.',
    ),

    'theme_color' => env('PWA_THEME_COLOR', '#1f1f1f'),

    'background_color' => env('PWA_BACKGROUND_COLOR', '#1f1f1f'),

    'status_bar_style' => env('PWA_STATUS_BAR_STYLE', 'black'),

    'short_name' => env('PWA_SHORT_NAME'),

    'shortcuts' => [
        ['name' => 'Home', 'short_name' => 'Home', 'route' => 'home'],
        ['name' => 'Cooks', 'short_name' => 'Cooks', 'route' => 'cooks'],
        ['name' => 'Stats', 'short_name' => 'Stats', 'route' => 'stats'],
    ],

];
