@php
    use App\Support\PwaAsset;

    $splashScreens = [
        ['file' => 'iphone-se-portrait.png', 'media' => '(device-width: 375px) and (-webkit-device-pixel-ratio: 2) and (orientation: portrait)'],
        ['file' => 'iphone-x-portrait.png', 'media' => '(device-width: 375px) and (-webkit-device-pixel-ratio: 3) and (orientation: portrait)'],
        ['file' => 'iphone-xr-portrait.png', 'media' => '(device-width: 414px) and (-webkit-device-pixel-ratio: 2) and (orientation: portrait)'],
        ['file' => 'iphone-xs-max-portrait.png', 'media' => '(device-width: 414px) and (-webkit-device-pixel-ratio: 3) and (orientation: portrait)'],
        ['file' => 'iphone-14-portrait.png', 'media' => '(device-width: 390px) and (-webkit-device-pixel-ratio: 3) and (orientation: portrait)'],
        ['file' => 'iphone-16-portrait.png', 'media' => '(device-width: 393px) and (-webkit-device-pixel-ratio: 3) and (orientation: portrait)'],
        ['file' => 'iphone-14-plus-portrait.png', 'media' => '(device-width: 428px) and (-webkit-device-pixel-ratio: 3) and (orientation: portrait)'],
        ['file' => 'iphone-14-pro-max-portrait.png', 'media' => '(device-width: 430px) and (-webkit-device-pixel-ratio: 3) and (orientation: portrait)'],
        ['file' => 'iphone-16-pro-portrait.png', 'media' => '(device-width: 402px) and (-webkit-device-pixel-ratio: 3) and (orientation: portrait)'],
        ['file' => 'iphone-16-pro-max-portrait.png', 'media' => '(device-width: 440px) and (-webkit-device-pixel-ratio: 3) and (orientation: portrait)'],
        ['file' => 'ipad-pro-12-portrait.png', 'media' => '(device-width: 1024px) and (-webkit-device-pixel-ratio: 2) and (orientation: portrait)'],
    ];
@endphp

<meta name="apple-mobile-web-app-capable" content="no" />
<meta name="apple-mobile-web-app-title" content="{{ config('app.name') }}" />
<meta name="apple-mobile-web-app-status-bar-style" content="{{ config('pwa.status_bar_style') }}" />

@foreach ($splashScreens as $screen)
    <link rel="apple-touch-startup-image" href="{{ PwaAsset::stableUrl('pwa-splash/'.$screen['file']) }}" media="{{ $screen['media'] }}" />
@endforeach
