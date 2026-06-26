@props(['beganAt' => null])

@php
    $initialElapsed = filled($beganAt) ? \App\Support\FormatElapsed::fromIso8601($beganAt) : null;
    $isConnecting = blank($beganAt);
@endphp

<div
    wire:ignore
    data-live-cook-timer
    @if (filled($beganAt))
        data-began-at="{{ $beganAt }}"
    @endif
    {{ $attributes->class([
        'inline-flex items-center gap-2 rounded-full border px-3 py-1 text-sm font-medium',
        'border-amber-500/30 bg-amber-500/10 text-amber-700 dark:text-amber-400' => $isConnecting,
        'border-red-500/30 bg-red-500/10 text-red-600 dark:text-red-400' => ! $isConnecting,
    ]) }}
    x-data="liveCookTimer(@js($beganAt))"
>
    <span class="relative flex size-2">
        <span
            class="absolute inline-flex size-full animate-ping rounded-full opacity-75"
            :class="connecting ? 'bg-amber-400' : 'bg-red-400'"
        ></span>
        <span
            class="relative inline-flex size-2 rounded-full"
            :class="connecting ? 'bg-amber-500' : 'bg-red-500'"
        ></span>
    </span>
    <span x-show="connecting">Connecting…</span>
    <span x-show="! connecting" x-cloak>LIVE</span>
    <span x-show="! connecting" x-text="elapsed" x-cloak class="tabular-nums">{{ $initialElapsed }}</span>
</div>
