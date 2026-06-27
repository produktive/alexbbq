<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<meta name="csrf-token" content="{{ csrf_token() }}" />
<meta name="theme-color" content="#4C05A3" />
@if (filled(config('webpush.vapid.public_key')))
    <meta name="vapid-public-key" content="{{ config('webpush.vapid.public_key') }}" />
@endif

<title>
    {{ filled($title ?? null) ? $title.' - '.config('app.name', 'Laravel') : config('app.name', 'Laravel') }}
</title>

<link rel="manifest" href="/manifest.webmanifest">
<link rel="icon" href="/favicon.ico" sizes="any">
<link rel="icon" href="/favicon.svg" type="image/svg+xml">
<link rel="apple-touch-icon" href="/apple-touch-icon.png">

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
