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

    public bool $pushEnabled = false;

    public function mount(): void
    {
        $settings = UserSettings::forUser(Auth::user());

        $this->form->fill($settings->toAlertFormState());
        $this->pushEnabled = Auth::user()->pushSubscriptions()->exists();
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

    public function refreshPushStatus(): void
    {
        $this->pushEnabled = Auth::user()->pushSubscriptions()->exists();
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
        <flux:card class="space-y-4">
            <div class="flex flex-col items-start gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <flux:heading size="lg">Push Notifications</flux:heading>
                    <flux:text>
                        Enable browser push notifications to receive temperature alerts on this device.
                    </flux:text>
                </div>

                <flux:badge
                    :color="$pushEnabled ? 'green' : 'zinc'"
                    size="sm"
                    class="w-fit shrink-0"
                >
                    {{ $pushEnabled ? __('Enabled on this device') : __('Not enabled') }}
                </flux:badge>
            </div>

            <div class="flex flex-wrap gap-3">
                <flux:button
                    variant="primary"
                    icon="bell-alert"
                    x-data
                    x-on:click="window.pushNotifications.enable().then(() => $wire.refreshPushStatus())"
                >
                    Enable Push Notifications
                </flux:button>

                <flux:button
                    variant="ghost"
                    icon="bell-slash"
                    x-data
                    x-on:click="window.pushNotifications.disable().then(() => $wire.refreshPushStatus())"
                >
                    Disable Push Notifications
                </flux:button>
            </div>
        </flux:card>

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
