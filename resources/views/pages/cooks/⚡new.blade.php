<?php

use App\Filament\Schemas\AlertsSection;
use App\Filament\Schemas\CookSection;
use App\Models\Cook;
use App\Filament\Plugins\LinkNewTabPlugin;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Process;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Attributes\Computed;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Concerns\InteractsWithForms;

new
#[Title('Start New Cook')]
class extends Component implements HasActions, HasForms {
    use InteractsWithActions;
    use InteractsWithForms;

    public bool $live = false;
    public ?array $data = [];

    public function mount(): void
    {
        $this->live = Process::run('pgrep Table')->successful();

        if ($this->live) {
            $this->redirectRoute('home', navigate: true);
            return;
        }

        $this->form->fill();
    }

    public function form(Schema $form): Schema
    {
        return $form
            ->statePath('data')
            ->components([
                CookSection::make(),
                AlertsSection::make(collapse: true),
            ]);
    }

    public function save(): void
    {
        $state = $this->form->getState();
        dd($state);

        // Save your model here
        // Example:
        // Post::create($state);
    }
}
?>

<flux:container>
    <style>
        .bbq-slider .noUi-connect {
            background: rgb(34 197 94); /* green */
        }

        .food-slider .noUi-connect {
            background: rgb(234 88 12);
        }
    </style>
    <flux:heading
        level="1"
        size="xl"
    >
        Start New Cook
    </flux:heading>
    <div class="max-w-3xl my-6">
        {{ $this->form }}

        <div class="flex justify-end my-6">
            <flux:button
                variant="primary"
                wire:click="save"
            >
                Start Recording
            </flux:button>
        </div>
    </div>

    <x-filament-actions::modals/>
</flux:container>
