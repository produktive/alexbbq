<?php

use App\Models\Cook;
use App\Services\MaverickService;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component {
    public ?int $activeCookId = null;

    public ?string $beganAt = null;

    public bool $maverickRunning = false;

    public function mount(): void
    {
        $this->sync();
    }

    public function pollLiveCookSync(): void
    {
        $previousCookId = $this->activeCookId;

        $this->sync();

        if ($previousCookId !== null && $this->activeCookId === null) {
            $this->dispatch('cook-stopped');
        }
    }

    private function sync(): void
    {
        $cook = Cook::active();

        $this->activeCookId = $cook?->id;
        $this->beganAt = $cook?->getBeganAt()->toIso8601String();
        $this->maverickRunning = app(MaverickService::class)->isRunning();

        $this->dispatch(
            'live-cook-updated',
            activeCookId: $this->activeCookId,
            beganAt: $this->beganAt,
            maverickRunning: $this->maverickRunning,
        );
    }
}
?>
<div wire:poll.12s.visible="pollLiveCookSync" class="hidden" aria-hidden="true"></div>
