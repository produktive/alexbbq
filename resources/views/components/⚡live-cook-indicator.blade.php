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
            <div
                wire:ignore
                data-live-cook-timer
                class="inline-flex items-center gap-2 rounded-full border border-red-500/30 bg-red-500/10 px-3 py-1 text-sm font-medium text-red-600 dark:text-red-400"
                x-data="liveCookTimer(beganAt)"
                x-bind:key="beganAt"
            >
                <span class="relative flex size-2">
                    <span class="absolute inline-flex size-full animate-ping rounded-full bg-red-400 opacity-75"></span>
                    <span class="relative inline-flex size-2 rounded-full bg-red-500"></span>
                </span>
                <span>LIVE</span>
                <span x-text="elapsed" class="tabular-nums"></span>
            </div>
        </a>
    </template>
</div>
