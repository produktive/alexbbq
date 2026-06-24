<?php

namespace App\Livewire\Concerns;

use App\Models\Cook;
use Livewire\Attributes\On;

trait PollsLiveCookData
{
    public function pollLiveCookUpdates(): void
    {
        $activeCookId = Cook::active()?->id;

        if ($activeCookId !== null && $this->displayCookId !== $activeCookId) {
            $this->displayCookId = $activeCookId;
            $this->resetLiveCookComputedProperties();

            return;
        }

        if ($activeCookId === null) {
            if ($this->displayCook?->isActive() ?? false) {
                $this->displayCookId = Cook::mostRecent()?->id;
                $this->resetLiveCookComputedProperties();
                $this->dispatch('cook-chart-updated');
            } else {
                unset($this->isLive);
            }

            return;
        }

        $this->displayCook?->unsetRelation('readings');
        $this->resetLiveCookComputedProperties();
        $this->dispatch('cook-chart-updated');
    }

    protected function resetLiveCookComputedProperties(): void
    {
        unset($this->displayCook, $this->chartData, $this->isLive);
    }

    #[On('cook-stopped')]
    public function handleCookStopped(): void
    {
        $this->pollLiveCookUpdates();
    }
}
