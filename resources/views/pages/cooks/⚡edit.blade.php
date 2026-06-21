<?php

use App\Filament\Schemas\CookSection;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Schemas\Schema;
use App\Models\Cook;
use Livewire\Component;

new class extends Component implements HasActions, HasForms {
    use InteractsWithActions;
    use InteractsWithForms;

    public Cook $cook;
    public ?array $data = [];

    public function mount(Cook $cook): void
    {
        $this->cook = $cook;
        $this->form->fill([
            'smoker_id' => $this->cook->smoker_id,
            'title' => $this->cook->title,
            'description' => $this->cook->description,
        ]);
    }

    public function form(Schema $form): Schema
    {
        return $form
            ->statePath('data')
            ->components([
                CookSection::make()
            ]);
    }

    public function update(): void
    {
        $data = $this->form->getState();
        $this->cook->update($data);
        $this->redirectRoute('cooks.view', $this->cook);
    }
}
?>

<flux:container>
    <flux:heading level="1" size="xl">
        Edit Cook
    </flux:heading>

    <div class="max-w-3xl my-6">
        {{ $this->form }}

        <div class="flex justify-end gap-4 my-6">
            <flux:button href="{{ route('cooks.view', $this->cook) }}">
                Cancel
            </flux:button>

            <flux:button variant="primary" wire:click="update">
                Update
            </flux:button>
        </div>
    </div>

    <x-filament-actions::modals />
</flux:container>
