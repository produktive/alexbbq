@props([
    'cook',
    'isLive' => false,
])

@php
    $beganAt = $cook->getBeganAt() ?? $cook->created_at;
    $beganLabel = $beganAt->format('F j, Y \a\t g:i A');
@endphp

<div {{ $attributes->class('cook-header shrink-0') }}>
    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div class="min-w-0 flex-1">
            <flux:heading level="1" size="xl">
                {{ $cook->title }}
            </flux:heading>

            @if ($isLive)
                <div
                    class="mt-2 flex flex-col gap-2"
                    x-data="{
                        cookId: @js($cook->id),
                        beganAt: @js($cook->getBeganAt()?->toIso8601String()),
                        beganAtLabel: @js($beganLabel),
                        hasReadings: @js($cook->hasReadings()),
                    }"
                    x-on:live-cook-status.window="
                        if ($event.detail.activeCookId === cookId) {
                            beganAt = $event.detail.beganAt;
                            beganAtLabel = $event.detail.beganAtLabel;
                        }
                    "
                >
                    @unless ($cook->hasReadings())
                        <flux:callout
                            x-show="! beganAt"
                            x-cloak
                            icon="signal"
                            variant="warning"
                        >
                            Waiting for the first temperature reading from your probe…
                        </flux:callout>
                    @endunless

                    <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                        @auth
                            <x-live-cook-timer
                                show-in-title-area
                                :began-at="$cook->getBeganAt()?->toIso8601String()"
                                x-bind:data-began-at="beganAt"
                            />
                        @endauth

                        <flux:text
                            size="sm"
                            class="text-zinc-500 dark:text-zinc-400"
                            x-show="hasReadings || beganAt"
                            x-cloak
                        >
                            Began <span x-text="beganAtLabel"></span>
                        </flux:text>
                    </div>
                </div>
            @else
                <flux:text size="sm" class="mt-2 text-zinc-500 dark:text-zinc-400">
                    {{ $beganLabel }} · {{ $cook->getDurationLabel() }}
                </flux:text>
            @endif
        </div>

        @isset($actions)
            <div class="flex shrink-0 items-center gap-2 sm:pt-1">
                {{ $actions }}
            </div>
        @endisset
    </div>
</div>
