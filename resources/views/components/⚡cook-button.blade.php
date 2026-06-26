<?php

use App\Models\Cook;
use App\Models\Smoker;
use App\Services\MaverickService;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Support\Icons\Heroicon;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component implements HasActions, HasSchemas {
    use InteractsWithActions;
    use InteractsWithSchemas;

    public bool $live = false;

    public function mount(): void
    {
        $this->syncLiveState();
    }

    public function syncLiveFromStatus(?int $activeCookId, bool $maverickRunning): void
    {
        $this->live = $maverickRunning || $activeCookId !== null;
    }

    #[On('cook-stopped')]
    public function handleCookStopped(): void
    {
        $this->syncLiveState();
    }

    #[Computed]
    public function text(): string
    {
        return $this->live ? 'Stop Cook' : 'Start New Cook';
    }

    #[Computed]
    public function icon(): string
    {
        return $this->live ? 'stop-circle' : 'play-circle';
    }

    public function stopCookAction(): Action
    {
        return Action::make('stopCook')
            ->label(__('Stop Cook'))
            ->color('danger')
            ->icon(Heroicon::StopCircle)
            ->requiresConfirmation()
            ->modalHeading(__('Stop recording?'))
            ->modalDescription(__('This will stop temperature recording for the current cook.'))
            ->modalSubmitActionLabel(__('Stop Cook'))
            ->action(function (): void {
                app(MaverickService::class)->stop();

                $this->syncLiveState();
            });
    }

    public function startCook(): void
    {
        if (! Smoker::query()->exists()) {
            Flux::toast(
                text: __('Create a smoker to start a cook.'),
                heading: __('Smoker Required'),
                variant: 'warning'
            );

            $this->redirectRoute('smokers', navigate: true);

            return;
        }

        $this->redirectRoute('cooks.new', navigate: true);
    }

    private function syncLiveState(): void
    {
        $this->live = app(MaverickService::class)->isRunning() || Cook::active() !== null;
    }
}
?>

<div>
    <flux:sidebar.nav>
        @if ($this->live)
            <flux:button
                variant="danger"
                icon="stop-circle"
                wire:click="mountAction('stopCook')"
            >
                {{ __('Stop Cook') }}
            </flux:button>
        @else
            <flux:button
                :icon="$this->icon"
                wire:click="startCook"
            >
                {{ $this->text }}
            </flux:button>
        @endif
    </flux:sidebar.nav>

    <x-filament-actions::modals />
</div>

@script
<script>
    window.addEventListener('live-cook-status', (event) => {
        $wire.syncLiveFromStatus(event.detail.activeCookId, event.detail.maverickRunning);
    });
</script>
@endscript
