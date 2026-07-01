@props([
    'label',
    'icon',
    'variant',
])

<div {{ $attributes->class(['stats-card', "stats-card--{$variant}"]) }}>
    <div class="stats-card-header">
        <div class="stats-card-icon">
            <x-dynamic-component :component="'flux::icon.'.$icon" class="size-5" />
        </div>

        <flux:text size="sm" class="stats-card-label">{{ $label }}</flux:text>
    </div>

    <div class="stats-card-value">
        {{ $slot }}
    </div>
</div>
