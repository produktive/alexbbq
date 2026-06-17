<?php

use App\Filament\Schemas\AlertsSection;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Schemas\Schema;
use Livewire\Attributes\Title;
use Livewire\Component;

new
#[Title('Alerts')]
class extends Component implements HasForms {
    use InteractsWithForms;

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $form): Schema
    {
        return $form
            ->statePath('data')
            ->components([
                AlertsSection::make(),
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
        icon="bell-alert"
        level="1"
        size="xl"
    >
        Alerts
    </flux:heading>

    <flux:text>
        Set your minimum and maximum temperature alerts for this cook.
    </flux:text>

    <div class="max-w-3xl my-6">
        {{ $this->form }}

        <div class="flex justify-end mt-6">
            <flux:button
                variant="primary"
                wire:click="save"
            >
                Save Settings
            </flux:button>
        </div>
    </div>
</flux:container>
