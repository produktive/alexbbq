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

    #[On('live-cook-status')]
    public function syncFromStatus(?int $activeCookId = null, ?string $beganAt = null): void
    {
        $this->cookId = $activeCookId;
        $this->beganAt = $beganAt;
    }

    private function syncFromActiveCook(): void
    {
        $cook = Cook::active();

        $this->cookId = $cook?->id;
        $this->beganAt = $cook?->getBeganAt()?->toIso8601String();
    }
}
?>
<div
    x-data="{ online: navigator.onLine }"
    x-on:online.window="online = true"
    x-on:offline.window="online = false"
>
    @if ($cookId)
        <a href="{{ route('home') }}" wire:navigate x-show="online" x-cloak>
            <x-live-cook-timer :began-at="$beganAt" />
        </a>
    @endif
</div>

@script
<script>
    window.addEventListener('live-cook-status', (event) => {
        $wire.syncFromStatus(
            event.detail.activeCookId ?? null,
            event.detail.beganAt ?? null,
        );
    });
</script>
@endscript
