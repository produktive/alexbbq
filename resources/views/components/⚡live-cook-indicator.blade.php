<?php

use App\Models\Cook;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component {

    public ?int $cookId = null;

    public ?string $beganAt = null;

    public function mount(): void
    {
        $this->syncActiveCook();
    }

    public function pollLiveCookIndicator(): void
    {
        $this->syncActiveCook();
    }

    #[On('cook-stopped')]
    public function handleCookStopped(): void
    {
        $this->syncActiveCook();
    }

    private function syncActiveCook(): void
    {
        $cook = Cook::active();

        $this->cookId = $cook?->id;
        $this->beganAt = $cook?->getBeganAt()->toIso8601String();
    }
}
?>
<div wire:poll.12s.visible="pollLiveCookIndicator">
    @if ($cookId)
        <a href="{{ route('home') }}" wire:navigate wire:key="live-cook-indicator-{{ $cookId }}">
            <x-live-cook-timer :began-at="$beganAt" />
        </a>
    @endif
</div>
