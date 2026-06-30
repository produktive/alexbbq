@props([
    'beganAt' => null,
    'showInTitleArea' => false,
])

@php
    $initialElapsed = filled($beganAt) ? \App\Support\FormatElapsed::fromIso8601($beganAt) : null;
    $isConnecting = blank($beganAt);
    $displayClass = $showInTitleArea ? 'hidden lg:inline-flex' : 'inline-flex';
@endphp

<div
    wire:ignore
    data-live-cook-timer
    @if (filled($beganAt))
        data-began-at="{{ $beganAt }}"
    @endif
    {{ $attributes->class("$displayClass items-center gap-2 rounded-full border px-3 py-1 text-sm font-medium") }}
    :class="connecting
        ? 'border-amber-500/30 bg-amber-500/10 text-amber-700 dark:text-amber-400'
        : 'border-red-500/30 bg-red-500/10 text-red-600 dark:text-red-400'"
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
    <span x-show="connecting" @class(['hidden' => ! $isConnecting])>Connecting…</span>
    <span x-show="! connecting" x-cloak :class="{ 'hidden': connecting }">LIVE</span>
    <span x-show="! connecting" x-text="elapsed" x-cloak :class="{ 'hidden': connecting }" class="tabular-nums">{{ $initialElapsed }}</span>
</div>
