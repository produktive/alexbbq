<?php

use App\Filament\Schemas\AlertsSection;
use App\Models\UserSettings;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Schemas\Schema;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Component;

new
#[Title('Alerts')]
class extends Component implements HasForms {
    use InteractsWithForms;

    public ?array $data = [];

    public function mount(): void
    {
        $settings = UserSettings::forUser(Auth::user());

        $this->form->fill($settings->toAlertFormState());
    }

    public function form(Schema $form): Schema
    {
        return $form
            ->statePath('data')
            ->components([
                AlertsSection::make(includeFrequency: true),
            ]);
    }

    public function save(): void
    {
        $state = $this->form->getState();
        $settings = UserSettings::forUser(Auth::user());

        $settings->fillFromAlertFormState($state);
        $settings->save();

        Flux::toast(variant: 'success', text: __('Alert settings saved.'));
    }
}
?>

<flux:container>
    <flux:heading
        icon="bell-alert"
        level="1"
        size="xl"
    >
        Alerts
    </flux:heading>

    <flux:text>
        Set temperature thresholds and push notification preferences for live cooks.
    </flux:text>

    <div class="max-w-3xl my-6 space-y-6">
        <livewire:push-notifications-toggle />

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
