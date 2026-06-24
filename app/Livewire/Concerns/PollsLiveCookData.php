<?php

namespace App\Livewire\Concerns;

use App\Models\Cook;
use App\Models\Reading;
use App\Services\TemperatureAlertService;

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

        $this->evaluateAlertsForCook($this->displayCookId);
    }

    protected function resetLiveCookComputedProperties(): void
    {
        unset($this->displayCook, $this->chartData, $this->isLive);
    }

    protected function evaluateAlertsForCook(?int $cookId): void
    {
        if ($cookId === null) {
            return;
        }

        $reading = Reading::query()
            ->where('cook_id', $cookId)
            ->latest('id')
            ->first();

        if ($reading === null) {
            return;
        }

        app(TemperatureAlertService::class)->evaluate($reading);
    }
}
