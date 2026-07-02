<?php

return [

    'description' => env(
        'PWA_DESCRIPTION',
        'Live BBQ temperature monitoring for the Maverick ET-732.',
    ),

    'theme_color' => env('PWA_THEME_COLOR', '#18181b'),

    'background_color' => env('PWA_BACKGROUND_COLOR', '#ffffff'),

    'dark_theme_color' => env('PWA_DARK_THEME_COLOR', '#18181b'),

    'dark_background_color' => env('PWA_DARK_BACKGROUND_COLOR', '#18181b'),

    'icon_background_color' => env('PWA_ICON_BACKGROUND_COLOR', '#ffffff'),

    'dark_icon_background_color' => env('PWA_DARK_ICON_BACKGROUND_COLOR', '#171717'),

    'short_name' => env('PWA_SHORT_NAME'),

];
