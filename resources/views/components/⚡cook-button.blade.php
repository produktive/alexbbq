<?php

use App\Models\Cook;
use App\Filament\Plugins\LinkNewTabPlugin;
use App\Models\Smoker;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\Select;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Process;
use Livewire\Component;
use Livewire\Attributes\Computed;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Concerns\InteractsWithForms;

new class extends Component implements HasActions, HasForms {
    use InteractsWithActions;
    use InteractsWithForms;

    public bool $live = false;
    public ?array $data = [];

    public function mount(): void
    {
        $this->live = Process::run('pgrep maverick')->successful();
    }

    #[Computed]
    public function variant(): string
    {
        return $this->live ? 'danger' : 'primary';
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

    public function toggleCook(): void
    {
        if ($this->live) {
            $this->live = ! Process::run('pkill maverick')->successful();
            return;
        }

        if (! Smoker::query()->exists()) {
            Flux::toast(
                text: __('Create a smoker to start a cook.'),
                heading: __('Smoker Required'),
                variant: 'warning'
            );

            $this->redirectRoute('smokers', navigate: true);
            return;
        }

        $this->redirectRoute('cooks.new');
    }
}
?>

<flux:sidebar.nav>
    @if ($this->live)
        <flux:button
            variant="danger"
            :icon="$this->icon"
            wire:click="toggleCook"
        >
            {{ $this->text }}
        </flux:button>
    @else
        <flux:button
            :icon="$this->icon"
            wire:click="toggleCook"
        >
            {{ $this->text }}
        </flux:button>
    @endif
</flux:sidebar.nav>
