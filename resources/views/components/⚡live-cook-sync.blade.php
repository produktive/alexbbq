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

    #[On('echo:cooks,LiveCookUpdated')]
    public function onLiveCookUpdated(
        string $type,
        ?int $cookId = null,
        ?string $beganAt = null,
        bool $maverickRunning = false,
    ): void {
        $previousCookId = $this->activeCookId;

        if ($type === 'started') {
            $this->activeCookId = $cookId;
            $this->beganAt = $beganAt;
            $this->maverickRunning = $maverickRunning;
            $this->dispatchLiveCookUpdated();

            return;
        }

        if ($type === 'stopped') {
            $this->activeCookId = null;
            $this->beganAt = null;
            $this->maverickRunning = false;
            $this->dispatchLiveCookUpdated();

            if ($previousCookId !== null) {
                $this->dispatch('cook-stopped');
            }
        }
    }

    private function sync(): void
    {
        $cook = Cook::active();

        $this->activeCookId = $cook?->id;
        $this->beganAt = $cook?->getBeganAt()->toIso8601String();
        $this->maverickRunning = app(MaverickService::class)->isRunning();

        $this->dispatchLiveCookUpdated();
    }

    private function dispatchLiveCookUpdated(): void
    {
        $this->dispatch(
            'live-cook-updated',
            activeCookId: $this->activeCookId,
            beganAt: $this->beganAt,
            maverickRunning: $this->maverickRunning,
        );
    }
}
?>
<div class="hidden" aria-hidden="true"></div>
