@props(['beganAt' => null])

<div
    wire:ignore
    data-live-cook-timer
    @if (filled($beganAt))
        data-began-at="{{ $beganAt }}"
    @endif
    {{ $attributes->class('inline-flex items-center gap-2 rounded-full border border-red-500/30 bg-red-500/10 px-3 py-1 text-sm font-medium text-red-600 dark:text-red-400') }}
    x-data="liveCookTimer()"
>
    <span class="relative flex size-2">
        <span class="absolute inline-flex size-full animate-ping rounded-full bg-red-400 opacity-75"></span>
        <span class="relative inline-flex size-2 rounded-full bg-red-500"></span>
    </span>
    <span>LIVE</span>
    <span x-text="elapsed" class="tabular-nums"></span>
</div>
