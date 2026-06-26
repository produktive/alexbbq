<?php

namespace App\Livewire\Concerns;

use App\Models\Cook;
use Livewire\Attributes\On;

trait ListensForLiveCookUpdates
{
    #[On('echo:cooks,.LiveCookUpdated')]
    public function onLiveCookUpdated(string $type, ?int $cookId = null): void
    {
        match ($type) {
            'started' => $this->applyLiveCookStarted($cookId),
            'stopped' => $this->applyLiveCookStopped(),
            'reading' => $this->applyLiveCookReading($cookId),
            default => null,
        };
    }

    #[On('cook-stopped')]
    public function handleCookStopped(): void
    {
        $this->applyLiveCookStopped();
    }

    protected function applyLiveCookStarted(?int $cookId): void
    {
        if ($cookId !== null && $this->displayCookId !== $cookId) {
            $this->displayCookId = $cookId;
            $this->resetLiveCookComputedProperties();
            $this->dispatch('cook-chart-updated');
        }
    }

    protected function applyLiveCookStopped(): void
    {
        if ($this->displayCook?->isActive() ?? false) {
            $this->displayCookId = Cook::mostRecent()?->id;
            $this->resetLiveCookComputedProperties();
            $this->dispatch('cook-chart-updated');
        } else {
            unset($this->isLive);
        }
    }

    protected function applyLiveCookReading(?int $cookId): void
    {
        $activeCookId = Cook::active()?->id;

        if ($activeCookId === null) {
            return;
        }

        if ($this->displayCookId !== $activeCookId) {
            $this->displayCookId = $activeCookId;
            $this->resetLiveCookComputedProperties();

            return;
        }

        if ($cookId !== null && $cookId !== $this->displayCookId) {
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
