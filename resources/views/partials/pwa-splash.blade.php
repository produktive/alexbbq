@php
    use App\Support\PwaAsset;

    $splashScreens = [
        ['file' => 'fallback-portrait.png', 'media' => null],
        ['file' => 'iphone-se-portrait.png', 'media' => 'screen and (device-width: 375px) and (device-height: 667px) and (-webkit-device-pixel-ratio: 2) and (orientation: portrait)'],
        ['file' => 'iphone-x-portrait.png', 'media' => 'screen and (device-width: 375px) and (device-height: 812px) and (-webkit-device-pixel-ratio: 3) and (orientation: portrait)'],
        ['file' => 'iphone-xr-portrait.png', 'media' => 'screen and (device-width: 414px) and (device-height: 896px) and (-webkit-device-pixel-ratio: 2) and (orientation: portrait)'],
        ['file' => 'iphone-xs-max-portrait.png', 'media' => 'screen and (device-width: 414px) and (device-height: 896px) and (-webkit-device-pixel-ratio: 3) and (orientation: portrait)'],
        ['file' => 'iphone-14-portrait.png', 'media' => 'screen and (device-width: 390px) and (device-height: 844px) and (-webkit-device-pixel-ratio: 3) and (orientation: portrait)'],
        ['file' => 'iphone-15-portrait.png', 'media' => 'screen and (device-width: 393px) and (device-height: 852px) and (-webkit-device-pixel-ratio: 3) and (orientation: portrait)'],
        ['file' => 'iphone-14-plus-portrait.png', 'media' => 'screen and (device-width: 428px) and (device-height: 926px) and (-webkit-device-pixel-ratio: 3) and (orientation: portrait)'],
        ['file' => 'iphone-14-pro-max-portrait.png', 'media' => 'screen and (device-width: 430px) and (device-height: 932px) and (-webkit-device-pixel-ratio: 3) and (orientation: portrait)'],
        ['file' => 'iphone-16-pro-portrait.png', 'media' => 'screen and (device-width: 402px) and (device-height: 874px) and (-webkit-device-pixel-ratio: 3) and (orientation: portrait)'],
        ['file' => 'iphone-16-pro-max-portrait.png', 'media' => 'screen and (device-width: 440px) and (device-height: 956px) and (-webkit-device-pixel-ratio: 3) and (orientation: portrait)'],
        ['file' => 'ipad-pro-12-portrait.png', 'media' => 'screen and (device-width: 1024px) and (device-height: 1366px) and (-webkit-device-pixel-ratio: 2) and (orientation: portrait)'],
    ];
@endphp

<meta name="apple-mobile-web-app-capable" content="yes" />
<meta name="apple-mobile-web-app-title" content="{{ config('app.name') }}" />
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent" />

{{-- iOS uses the last matching startup image. List the generic fallback first. --}}
@foreach ($splashScreens as $screen)
    @if (filled($screen['media']))
        <link rel="apple-touch-startup-image" href="{{ PwaAsset::stableUrl('pwa-splash/'.$screen['file']) }}" media="{{ $screen['media'] }}" />
    @else
        <link rel="apple-touch-startup-image" href="{{ PwaAsset::stableUrl('pwa-splash/'.$screen['file']) }}" />
    @endif
@endforeach
