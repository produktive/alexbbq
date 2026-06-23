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

    #[On('echo:live,.live-cook-changed')]
    public function onLiveCookChanged(array $event): void
    {
        match ($event['action'] ?? null) {
            'started' => $this->syncActiveCook(),
            'ended' => $this->reset('cookId', 'beganAt'),
            default => null,
        };
    }

    private function syncActiveCook(): void
    {
        $cook = Cook::active();

        $this->cookId = $cook?->id;
        $this->beganAt = $cook?->getBeganAt()->toIso8601String();
    }
}
?>
<div>
    @if ($cookId)
        <a href="{{ route('home') }}" wire:navigate>
            <x-live-cook-timer :began-at="$beganAt" />
        </a>
    @endif
</div>
