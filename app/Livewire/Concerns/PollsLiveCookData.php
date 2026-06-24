<?php

namespace App\Livewire\Concerns;

use App\Models\Cook;

trait PollsLiveCookData
{
    public function pollLiveCookUpdates(): void
    {
        $activeCook = Cook::active();
        $activeCookId = $activeCook?->id;

        if ($activeCookId !== null && $this->displayCookId !== $activeCookId) {
            $this->displayCookId = $activeCookId;
            $this->resetLiveCookComputedProperties();

            return;
        }

        if ($activeCookId === null && ($this->displayCook?->isActive() ?? false)) {
            $this->displayCookId = Cook::mostRecent()?->id;
            $this->resetLiveCookComputedProperties();

            return;
        }

        if (! ($this->displayCook?->isActive() ?? false)) {
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
}
