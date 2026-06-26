<?php

use App\Models\Cook;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    public ?int $displayCookId = null;

    public function mount(): void
    {
        $this->syncDisplayCook();
    }

    private function syncDisplayCook(): void
    {
        $preferredId = Cook::active()?->id ?? Cook::mostRecent()?->id;

        if ($this->displayCookId === $preferredId) {
            return;
        }

        $this->displayCookId = $preferredId;
        unset($this->displayCook, $this->isLive);
    }

    #[Computed]
    public function displayCook(): ?Cook
    {
        if ($this->displayCookId === null) {
            return null;
        }

        return Cook::query()->find($this->displayCookId);
    }

    #[Computed]
    public function isLive(): bool
    {
        return $this->displayCook?->isActive() ?? false;
    }
}
?>
<flux:container>
    @if ($this->displayCook)
        @auth
            @if ($this->isLive)
                <div
                    wire:key="live-cook-timer-{{ $this->displayCook->id }}"
                    class="my-2 hidden justify-end lg:flex"
                    x-data="{
                        cookId: @js($this->displayCook->id),
                        beganAt: @js($this->displayCook->getBeganAt()?->toIso8601String()),
                        beganAtLabel: @js($this->displayCook->getBeganAt()?->format('F j, Y \a\t g:i A')),
                    }"
                    x-on:live-cook-status.window="
                        if ($event.detail.activeCookId === cookId) {
                            beganAt = $event.detail.beganAt;
                            beganAtLabel = $event.detail.beganAtLabel;
                        }
                    "
                >
                    <x-live-cook-timer
                        :began-at="$this->displayCook->getBeganAt()?->toIso8601String()"
                        x-bind:data-began-at="beganAt"
                    />
                </div>
            @endif
        @endauth

        <flux:heading level="1" size="xl" class="my-2">
            {{ $this->displayCook->title }}
        </flux:heading>

        @if ($this->isLive)
            <div
                x-data="{
                    cookId: @js($this->displayCook->id),
                    beganAt: @js($this->displayCook->getBeganAt()?->toIso8601String()),
                    beganAtLabel: @js($this->displayCook->getBeganAt()?->format('F j, Y \a\t g:i A')),
                }"
                x-on:live-cook-status.window="
                    if ($event.detail.activeCookId === cookId) {
                        beganAt = $event.detail.beganAt;
                        beganAtLabel = $event.detail.beganAtLabel;
                    }
                "
            >
                @unless ($this->displayCook->hasReadings())
                    <flux:callout
                        x-show="! beganAt"
                        x-cloak
                        icon="signal"
                        variant="warning"
                        class="my-2"
                    >
                        Waiting for the first temperature reading from your probe…
                    </flux:callout>

                    <flux:text size="md" class="my-2" x-show="beganAt" x-cloak>
                        Began <span x-text="beganAtLabel"></span>
                    </flux:text>
                @else
                    <flux:text size="md" class="my-2">
                        Began {{ $this->displayCook->getBeganAt()->format('F j, Y \a\t g:i A') }}
                    </flux:text>
                @endunless
            </div>
        @else
            <flux:text size="md" class="my-2">
                Began {{ $this->displayCook->getBeganAt()?->format('F j, Y \a\t g:i A') ?? $this->displayCook->created_at->format('F j, Y \a\t g:i A') }}
            </flux:text>
        @endif

        <flux:text size="sm" class="my-2">
            {{ $this->displayCook->getDurationLabel() }}
        </flux:text>

        <div
            wire:key="home-chart-{{ $this->displayCook->id }}-{{ $this->isLive ? 'live' : 'static' }}"
            wire:ignore
            x-data="window.cookChart(null, false, @js($this->isLive), @js($this->displayCook->id))"
            class="relative h-125"
        >
            <div class="relative h-full">
                <canvas x-ref="canvas" class="block h-full w-full"></canvas>
            </div>

            <div
                x-show="loading"
                x-cloak
                class="absolute inset-0 flex items-center justify-center rounded-xl border border-neutral-200 bg-neutral-50/90 dark:border-neutral-700 dark:bg-neutral-900/90"
            >
                <div class="flex items-center gap-2 text-sm text-neutral-500 dark:text-neutral-400">
                    <flux:icon.loading variant="mini" />
                    Loading chart…
                </div>
            </div>

            <div
                x-show="loadError"
                x-cloak
                class="absolute inset-0 flex items-center justify-center rounded-xl border border-neutral-200 bg-neutral-50/90 dark:border-neutral-700 dark:bg-neutral-900/90"
            >
                <flux:text size="sm" class="text-neutral-500 dark:text-neutral-400">
                    Could not load chart data.
                </flux:text>
            </div>
        </div>

        <x-cook-description :html="$this->displayCook->renderRichContent('description')" />

        <div class="flex justify-end">
            @unless ($this->isLive)
                <flux:button href="{{ route('cooks.view', $this->displayCook) }}" wire:navigate>
                    View Full Cook
                </flux:button>
            @endunless
        </div>
    @else
        <flux:heading level="1" size="xl" class="my-2">
            No cooks yet
        </flux:heading>

        <flux:text class="my-2">
            When you record a cook, its chart will appear here.
        </flux:text>

        <div class="relative my-6 h-125 overflow-hidden rounded-xl border border-neutral-200 dark:border-neutral-700">
            <x-placeholder-pattern class="absolute inset-0 size-full stroke-gray-900/20 dark:stroke-neutral-100/20" />
        </div>
    @endif
</flux:container>
