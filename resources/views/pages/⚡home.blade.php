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

    public function syncFromLiveStatus(?int $activeCookId = null): void
    {
        $preferredId = $activeCookId ?? Cook::active()?->id ?? Cook::mostRecent()?->id;

        if ($this->displayCookId === $preferredId) {
            return;
        }

        $this->displayCookId = $preferredId;
        unset($this->displayCook, $this->isLive);
    }

    private function syncDisplayCook(): void
    {
        $this->syncFromLiveStatus();
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
        <x-cook-showcase
            :cook="$this->displayCook"
            :is-live="$this->isLive"
            :chart-wire-key="'home-chart-'.$this->displayCook->id.'-'.($this->isLive ? 'live' : 'static')"
        >
            @unless ($this->isLive)
                <x-slot:chartToolbar>
                    <flux:button href="{{ route('cooks.view', $this->displayCook) }}"
                         size="sm"
                         wire:navigate
                    >
                        View Cook Page
                    </flux:button>
                </x-slot:chartToolbar>
            @endunless
        </x-cook-showcase>
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

@script
<script>
    window.addEventListener('live-cook-status', (event) => {
        $wire.syncFromLiveStatus(event.detail.activeCookId ?? null);
    });

    document.addEventListener('livewire:navigated', () => {
        if (window.location.pathname === '/' || window.location.pathname === '') {
            $wire.syncFromLiveStatus();
        }
    });
</script>
@endscript
