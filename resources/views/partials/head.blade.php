<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover" />

@include('partials.pwa-splash')

<meta name="color-scheme" content="light dark" />
<meta name="csrf-token" content="{{ csrf_token() }}" />
<meta name="theme-color" content="{{ config('pwa.theme_color') }}" />
@if (filled(config('webpush.vapid.public_key')))
    <meta name="vapid-public-key" content="{{ config('webpush.vapid.public_key') }}" />
@endif

<title>
    {{ filled($title ?? null) ? $title.' - '.config('app.name', 'Laravel') : config('app.name', 'Laravel') }}
</title>

@php
    use App\Support\PwaAsset;
@endphp

<link rel="manifest" href="{{ route('manifest') }}">
<meta name="service-worker-url" content="{{ PwaAsset::serviceWorkerUrl() }}">
<link rel="apple-touch-icon" sizes="180x180" href="{{ PwaAsset::url('apple-touch-icon.png') }}">
<link rel="apple-touch-icon" sizes="192x192" href="{{ PwaAsset::url('pwa-icon-192.png') }}">
<link rel="apple-touch-icon" sizes="512x512" href="{{ PwaAsset::url('pwa-icon-512.png') }}">
<link rel="icon" href="/favicon.ico" sizes="any">
<link rel="icon" href="/favicon-light.svg" type="image/svg+xml" media="(prefers-color-scheme: light)">
<link rel="icon" href="/favicon-dark.svg" type="image/svg+xml" media="(prefers-color-scheme: dark)">
<link rel="icon" href="/favicon.svg" type="image/svg+xml">

@fonts

@if (config('broadcasting.default') === 'reverb' && filled(config('broadcasting.connections.reverb.key')))
    @php
        $reverbConfig = [
            'key' => config('broadcasting.connections.reverb.key'),
            'host' => config('broadcasting.connections.reverb.client.host'),
            'port' => config('broadcasting.connections.reverb.client.port'),
            'scheme' => config('broadcasting.connections.reverb.client.scheme'),
        ];
    @endphp
    <script>
        window.__reverbConfig = @json($reverbConfig);
    </script>
@endif

@vite(['resources/css/app.css', 'resources/js/app.js'])
@fluxAppearance
@filamentStyles
