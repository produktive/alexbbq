<?php

use App\Livewire\Concerns\PollsLiveCookData;
use App\Models\Cook;
use App\Support\CookChartData;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new class extends Component {
    use PollsLiveCookData;

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

        return Cook::query()->find($this->displayCookId);
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

    public function refreshChartData(): array
    {
        unset($this->chartData, $this->displayCook);

        return $this->chartData;
    }
}
?>
<flux:container wire:poll.12s.visible="pollLiveCookUpdates">
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
            x-data="window.cookChart(@js($this->chartData), false, @js($this->isLive))"
            class="relative h-125"
        >
            <div class="relative h-full">
                <canvas x-ref="canvas" class="block h-full w-full"></canvas>
            </div>
        </div>

        <div x-data="cookDescriptionGallery()" class="my-6">
            <article
                id="cook-description"
                x-ref="content"
                class="cook-description"
                :class="{ 'is-processed': processed }"
            >
                {!! $this->displayCook->renderRichContent('description') !!}
            </article>

            <template x-teleport="body">
                <div
                    x-show="lightboxOpen"
                    x-cloak
                    x-transition.opacity
                    class="fixed inset-0 z-50 flex items-center justify-center bg-black/85 p-4"
                    @keydown.escape.window="closeLightbox()"
                    @keydown.arrow-right.window="lightboxOpen && next()"
                    @keydown.arrow-left.window="lightboxOpen && prev()"
                >
                    <button
                        type="button"
                        class="absolute inset-0 cursor-default"
                        aria-label="Close lightbox"
                        @click="closeLightbox()"
                    ></button>

                    <button
                        type="button"
                        class="absolute inset-e-4 top-4 z-10 rounded-full bg-black/50 p-2 text-white hover:bg-black/70"
                        aria-label="Close"
                        @click="closeLightbox()"
                    >
                        <flux:icon.x-mark variant="mini" class="size-5"/>
                    </button>

                    <template x-if="hasMultiple()">
                        <button
                            type="button"
                            class="absolute inset-s-2 top-1/2 z-10 -translate-y-1/2 rounded-full bg-black/50 p-2 text-white hover:bg-black/70 sm:inset-s-4"
                            aria-label="Previous image"
                            @click.stop="prev()"
                        >
                            <flux:icon.chevron-left variant="mini" class="size-6"/>
                        </button>
                    </template>

                    <img
                        :src="lightboxSrc()"
                        :alt="lightboxAlt()"
                        class="relative z-1 max-h-[90vh] max-w-[90vw] rounded-lg object-contain shadow-2xl"
                        @click.stop
                    >

                    <template x-if="hasMultiple()">
                        <button
                            type="button"
                            class="absolute inset-e-2 top-1/2 z-10 -translate-y-1/2 rounded-full bg-black/50 p-2 text-white hover:bg-black/70 sm:inset-e-4"
                            aria-label="Next image"
                            @click.stop="next()"
                        >
                            <flux:icon.chevron-right variant="mini" class="size-6"/>
                        </button>
                    </template>
                </div>
            </template>
        </div>

        <div class="flex justify-end">
            @unless ($this->isLive)
                <flux:button href="{{ route('cooks.view', $this->displayCook) }}">
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
