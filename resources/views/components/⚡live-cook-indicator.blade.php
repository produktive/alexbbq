<?php

use App\Models\Cook;
use Livewire\Component;

new class extends Component {

    public ?int $cookId = null;

    public ?string $beganAt = null;

    public function mount(): void
    {
        $this->syncFromActiveCook();
    }

    private function syncFromActiveCook(): void
    {
        $cook = Cook::active();

        $this->cookId = $cook?->id;
        $this->beganAt = $cook?->getBeganAt()->toIso8601String();
    }
}
?>
<div
    x-data="{ cookId: @js($cookId), beganAt: @js($beganAt) }"
    x-on:live-cook-status.window="cookId = $event.detail.activeCookId; beganAt = $event.detail.beganAt"
>
    <template x-if="cookId">
        <a href="{{ route('home') }}" wire:navigate>
            <x-live-cook-timer x-bind:data-began-at="beganAt" />
        </a>
    </template>
</div>
