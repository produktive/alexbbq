@props([
    'cook',
    'isLive' => false,
    'editableChart' => false,
    'chartWireKey' => null,
])

<div {{ $attributes->class('cook-chart-viewport flex flex-col gap-4') }}>
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
            :wire-key="$chartWireKey"
        >
            @isset($chartToolbar)
                <x-slot:toolbar>{{ $chartToolbar }}</x-slot:toolbar>
            @endisset
        </x-cook-chart>

        @if (filled($cook->getRawOriginal('description')))
            <div class="cook-card-notes">
                <x-cook-description :html="$cook->renderRichContent('description')" />
            </div>
        @endif
    </div>
</div>
