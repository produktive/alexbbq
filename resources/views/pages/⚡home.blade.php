<?php

use App\Models\Cook;
use App\Support\CookChartData;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    public ?int $displayCookId = null;

    public function mount(): void
    {
        $this->syncDisplayCook();
    }

    public function hydrate(): void
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
        unset($this->displayCook, $this->chartData, $this->isLive);
    }

    #[Computed]
    public function displayCook(): ?Cook
    {
        if ($this->displayCookId === null) {
            return null;
        }

        return Cook::query()->with('readings')->find($this->displayCookId);
    }

    #[Computed]
    public function isLive(): bool
    {
        return $this->displayCook?->isActive() ?? false;
    }

    #[Computed]
    public function chartData(): array
    {
        return $this->displayCook
            ? CookChartData::fromCook($this->displayCook)
            : CookChartData::empty();
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
                >
                    <x-live-cook-timer :began-at="$this->displayCook->getBeganAt()->toIso8601String()" />
                </div>
            @endif
        @endauth

        <flux:heading level="1" size="xl" class="my-2">
            {{ $this->displayCook->title }}
        </flux:heading>

        <flux:text size="md" class="my-2">
            Began {{ $this->displayCook->getBeganAt()->format('F j, Y \a\t g:i A') }}
        </flux:text>

        @unless ($this->isLive && auth()->check())
            <flux:text size="sm" class="my-2">
                {{ $this->displayCook->getDurationLabel() }}
            </flux:text>
        @endunless

        <div
            wire:key="home-chart-{{ $this->displayCook->id }}-{{ $this->isLive ? 'live' : 'static' }}"
            wire:ignore
            x-data="window.cookChart(@js($this->chartData), false, @js($this->isLive), @js($this->displayCook->id))"
            class="relative h-125"
        >
            <div class="relative h-full">
                <canvas x-ref="canvas" class="block h-full w-full"></canvas>
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
