@props([
    'cook',
])

@php
    ['food' => $foodTemp, 'bbq' => $bbqTemp] = $cook->latestProbeTemps();
@endphp

<div
    {{ $attributes->class('grid grid-cols-2 gap-3') }}
    wire:ignore
    x-data="{
        cookId: @js($cook->id),
        food: @js($foodTemp),
        bbq: @js($bbqTemp),
        formatTemp(value) {
            return value === null ? '—' : `${value}°`;
        },
    }"
    x-on:cook-readings-updated.window="
        if (Number($event.detail.cookId) === cookId) {
            food = $event.detail.food;
            bbq = $event.detail.bbq;
        }
    "
>
    <div class="cook-probe-card cook-probe-card--food">
        <flux:text size="sm" class="cook-probe-card-label">Food</flux:text>
        <p class="cook-probe-card-value" x-text="formatTemp(food)">
            {{ $foodTemp === null ? '—' : "{$foodTemp}°" }}
        </p>
    </div>

    <div class="cook-probe-card cook-probe-card--bbq">
        <flux:text size="sm" class="cook-probe-card-label">BBQ</flux:text>
        <p class="cook-probe-card-value" x-text="formatTemp(bbq)">
            {{ $bbqTemp === null ? '—' : "{$bbqTemp}°" }}
        </p>
    </div>
</div>
