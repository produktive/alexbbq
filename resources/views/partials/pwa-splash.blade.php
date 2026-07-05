@php
    use App\Support\PwaAsset;

    $splashScreens = json_decode(
        file_get_contents(resource_path('data/pwa-splash-screens.json')),
        true,
        flags: JSON_THROW_ON_ERROR,
    );
@endphp

<meta name="apple-mobile-web-app-capable" content="yes" />
<meta name="apple-mobile-web-app-title" content="{{ config('app.name') }}" />
<meta name="apple-mobile-web-app-status-bar-style" content="{{ config('pwa.status_bar_style') }}" />

@foreach ($splashScreens as $screen)
    <link rel="apple-touch-startup-image" href="{{ PwaAsset::stableUrl('pwa-splash/v2/'.$screen['file']) }}" media="{{ $screen['media'] }}" />
@endforeach
