<?php

use App\Models\Cook;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component {
    public string $variant = 'sidebar';

    public bool $navigate = true;

    public int $count = 0;

    public function mount(string $variant = 'sidebar', bool $navigate = true): void
    {
        $this->variant = $variant;
        $this->navigate = $navigate;
        $this->syncCount();
    }

    #[On('cook-stopped')]
    public function handleCookStopped(): void
    {
        $this->syncCount();
    }

    public function syncCountFromStatus(int $count): void
    {
        $this->count = $count;
    }

    private function syncCount(): void
    {
        $this->count = Cook::finishedCount();
    }
}
?>
<div>
    @if ($variant === 'navbar' && $navigate)
        <flux:navbar.item
            :href="route('cooks')"
            :current="request()->routeIs('cooks')"
            icon="presentation-chart-line"
            wire:navigate
            badge="{{ $count }}"
        >
            {{ __('Cooks') }}
        </flux:navbar.item>
    @elseif ($variant === 'navbar')
        <flux:navbar.item
            :href="route('cooks')"
            :current="request()->routeIs('cooks')"
            icon="presentation-chart-line"
            badge="{{ $count }}"
        >
            {{ __('Cooks') }}
        </flux:navbar.item>
    @elseif ($navigate)
        <flux:sidebar.item
            icon="presentation-chart-line"
            :href="route('cooks')"
            :current="request()->routeIs('cooks')"
            wire:navigate
            badge="{{ $count }}"
        >
            {{ __('Cooks') }}
        </flux:sidebar.item>
    @else
        <flux:sidebar.item
            icon="presentation-chart-line"
            :href="route('cooks')"
            :current="request()->routeIs('cooks')"
            badge="{{ $count }}"
        >
            {{ __('Cooks') }}
        </flux:sidebar.item>
    @endif
</div>

@script
<script>
    window.addEventListener('live-cook-status', (event) => {
        if (event.detail.finishedCount !== undefined) {
            $wire.syncCountFromStatus(event.detail.finishedCount);
        }
    });
</script>
@endscript
