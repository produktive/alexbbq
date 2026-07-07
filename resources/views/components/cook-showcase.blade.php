@props([
    'cook',
    'isLive' => false,
    'editableChart' => false,
    'chartWireKey' => null,
])

@php
    $showcaseKey = $chartWireKey ?? 'cook-showcase-'.$cook->id.'-'.($isLive ? 'live' : 'static');
@endphp

<div wire:key="{{ $showcaseKey }}" {{ $attributes->class('cook-chart-viewport flex flex-col gap-4') }}>
    <x-cook-header :cook="$cook" :is-live="$isLive">
        @isset($actions)
            <x-slot:actions>
                {{ $actions }}
            </x-slot:actions>
        @endisset
    </x-cook-header>

    @if ($isLive)
        <x-cook-probe-cards :cook="$cook" />
    @endif

    <div class="cook-card">
        <x-cook-chart
            :cook="$cook"
            :editable="$editableChart"
            :live="$isLive"
        >
            @isset($chartMenu)
                <x-slot:menu>
                    {{ $chartMenu }}
                </x-slot:menu>
            @endisset
        </x-cook-chart>
    </div>

    @if (filled($cook->getRawOriginal('description')))
        <div class="cook-showcase-notes">
            <x-cook-description :html="$cook->renderRichContent('description')" />
        </div>
    @endif
</div>
