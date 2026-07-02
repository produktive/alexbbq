@php
    use App\Support\PwaAsset;

    $darkIconUrl = PwaAsset::url('pwa-icon-512.png');
    $lightIconUrl = PwaAsset::url('app-icon-light.png');
@endphp

<img
    src="{{ $darkIconUrl }}"
    alt=""
    {{ $attributes->class(['rounded-md object-cover']) }}
    x-bind:src="($flux.appearance === 'dark' || ($flux.appearance === 'system' && $flux.dark))
        ? @js($darkIconUrl)
        : @js($lightIconUrl)"
/>
