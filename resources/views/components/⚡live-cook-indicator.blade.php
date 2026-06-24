<?php

use App\Models\Cook;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component {

    public ?int $cookId = null;

    public ?string $beganAt = null;

    public function mount(): void
    {
        $this->syncFromActiveCook();
    }

    #[On('live-cook-updated')]
    public function handleLiveCookUpdated(?int $activeCookId = null, ?string $beganAt = null): void
    {
        $this->cookId = $activeCookId;
        $this->beganAt = $beganAt;
    }

    #[On('cook-stopped')]
    public function handleCookStopped(): void
    {
        $this->cookId = null;
        $this->beganAt = null;
    }

    private function syncFromActiveCook(): void
    {
        $cook = Cook::active();

        $this->cookId = $cook?->id;
        $this->beganAt = $cook?->getBeganAt()->toIso8601String();
    }
}
?>
<div>
    @if ($cookId)
        <a href="{{ route('home') }}" wire:navigate wire:key="live-cook-indicator-{{ $cookId }}">
            <x-live-cook-timer :began-at="$beganAt" />
        </a>
    @endif
</div>
