<?php

use App\Filament\Schemas\CookSection;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Schemas\Schema;
use App\Models\Cook;
use Livewire\Component;

new class extends Component implements HasForms {
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
};
?>

<flux:container>
    <flux:heading level="1" size="xl">
        Edit Cook
    </flux:heading>

    <div class="my-6">
        {{ $this->form }}
    </div>
</flux:container>
