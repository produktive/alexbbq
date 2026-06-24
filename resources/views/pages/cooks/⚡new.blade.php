<?php

use App\Filament\Schemas\AlertsSection;
use App\Filament\Schemas\CookSection;
use App\Models\UserSettings;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Schemas\Schema;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Process;
use Livewire\Attributes\Title;
use Livewire\Component;

new
#[Title('Start New Cook')]
class extends Component implements HasActions, HasForms {
    use InteractsWithActions;
    use InteractsWithForms;

    public bool $live = false;

    public ?array $data = [];

    public function mount(): void
    {
        $this->live = Process::run('pgrep maverick')->successful();

        if ($this->live) {
            $this->redirectRoute('home', navigate: true);

            return;
        }

        $this->form->fill(UserSettings::forUser(Auth::user())->toAlertFormState());
    }

    public function form(Schema $form): Schema
    {
        return $form
            ->statePath('data')
            ->components([
                CookSection::make(),
                AlertsSection::make(collapse: true, includeFrequency: true),
            ]);
    }

    public function save(): void
    {
        $state = $this->form->getState();

        $settings = UserSettings::forUser(Auth::user());
        $settings->fillFromAlertFormState($state);
        $settings->save();

        $maverick = base_path('maverick');

        if (! is_file($maverick) || ! is_executable($maverick)) {
            Flux::toast(
                variant: 'warning',
                text: __('Alert settings saved. Maverick is not available on this machine.'),
            );

            $this->redirectRoute('home', navigate: true);

            return;
        }

        Process::path(base_path())->start($maverick);

        Flux::toast(variant: 'success', text: __('Cook recording started.'));

        $this->redirectRoute('home', navigate: true);
    }
}
?>

<flux:container>
    <flux:heading level="1" size="xl">
        Start New Cook
    </flux:heading>

    <div class="max-w-3xl my-6">
        {{ $this->form }}

        <div class="flex justify-end my-6">
            <flux:button variant="primary" wire:click="save">
                Start Recording
            </flux:button>
        </div>
    </div>

    <x-filament-actions::modals />
</flux:container>
