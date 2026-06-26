<?php

use App\Events\LiveCookUpdated;
use App\Filament\Schemas\AlertsSection;
use App\Filament\Schemas\CookSection;
use App\Models\Cook;
use App\Models\Smoker;
use App\Models\UserSettings;
use App\Services\MaverickService;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Schemas\Schema;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
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
        $this->live = app(MaverickService::class)->isRunning();

        if ($this->live) {
            $this->redirectRoute('home', navigate: true);

            return;
        }

        $this->form->fill([
            ...UserSettings::forUser(Auth::user())->toAlertFormState(),
            'smoker_id' => Smoker::defaultForNewCook(),
        ]);
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

        $maverick = app(MaverickService::class);

        if (! $maverick->isAvailable()) {
            Flux::toast(
                variant: 'warning',
                text: __('Alert settings saved. Maverick is not available on this machine.'),
            );

            $this->redirectRoute('home', navigate: true);

            return;
        }

        if (Cook::active()) {
            Flux::toast(
                variant: 'warning',
                text: __('Alert settings saved. A cook is already in progress.'),
            );

            $this->redirectRoute('home', navigate: true);

            return;
        }

        $cook = Cook::query()->create([
            'user_id' => Auth::id(),
            'smoker_id' => $state['smoker_id'],
            'title' => $state['title'],
            'description' => $state['description'] ?? null,
        ]);

        Cook::flushRequestCache();

        if (! $maverick->start()) {
            $cook->delete();

            Cook::flushRequestCache();

            Flux::toast(
                variant: 'warning',
                text: __('Alert settings saved, but maverick failed to start. Check storage/logs/maverick.log.'),
            );

            $this->redirectRoute('home', navigate: true);

            return;
        }

        Flux::toast(variant: 'success', text: __('Cook recording started.'));

        broadcast(LiveCookUpdated::started($cook));

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
