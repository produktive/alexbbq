<?php

use App\Models\Cook;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Livewire\Component;

new class extends Component implements HasActions, HasSchemas {

    use InteractsWithActions;
    use InteractsWithSchemas;

    public Cook $cook;

    public function mount(Cook $cook): void
    {
        abort_if($cook->isActive(), 404);

        $this->cook = $cook;
    }

    public function render()
    {
        return $this->view()->title($this->cook->title);
    }

    private function ensureCanModifyReadings(): void
    {
        abort_unless($this->cook->isOwnedBy(auth()->id()), 403);
    }

    private function afterReadingsChanged(): void
    {
        $this->cook->syncStartTimeFromReadings();
        $this->cook->refresh();
        $this->cook->unsetRelation('readings');
    }

    private function notifyChartUpdated(bool $changed): void
    {
        if ($changed) {
            $this->dispatch('cook-chart-updated');
        }
    }

    private function confirmDeleteAction(
        string         $name,
        string|Closure $heading,
        string         $description,
        callable       $perform,
    ): Action
    {
        return Action::make($name)
            ->requiresConfirmation()
            ->modalHeading($heading)
            ->modalDescription($description)
            ->modalSubmitActionLabel('Delete')
            ->color('danger')
            ->action(function (array $arguments) use ($perform): void {
                $this->notifyChartUpdated($perform($arguments));
            });
    }

    private function performDeletePoint(int $id): bool
    {
        $this->ensureCanModifyReadings();

        if ($this->cook->readings()->whereKey($id)->delete() === 0) {
            return false;
        }

        $this->afterReadingsChanged();

        return true;
    }

    private function performDeleteBefore(int $id): bool
    {
        $this->ensureCanModifyReadings();

        $reading = $this->cook->readings()->findOrFail($id);

        if ($this->cook->readings()->where('time', '<', $reading->time)->delete() === 0) {
            return false;
        }

        $this->afterReadingsChanged();

        return true;
    }

    private function performDeleteAfter(int $id): bool
    {
        $this->ensureCanModifyReadings();

        $reading = $this->cook->readings()->findOrFail($id);

        if ($this->cook->readings()->where('time', '>', $reading->time)->delete() === 0) {
            return false;
        }

        $this->afterReadingsChanged();

        return true;
    }

    private function performDeleteSelected(array $ids): bool
    {
        $this->ensureCanModifyReadings();

        if ($this->cook->readings()->whereIn('id', $ids)->delete() === 0) {
            return false;
        }

        $this->afterReadingsChanged();

        return true;
    }

    private function performSaveNote(int $id, ?string $note): bool
    {
        $this->ensureCanModifyReadings();

        $reading = $this->cook->readings()->findOrFail($id);

        $reading->note = filled($note) ? $note : null;
        $reading->save();

        $this->cook->unsetRelation('readings');

        return true;
    }

    public function deletePointAction(): Action
    {
        return $this->confirmDeleteAction(
            'deletePoint',
            'Delete this point?',
            'This reading will be permanently removed from the cook chart.',
            fn(array $arguments) => $this->performDeletePoint((int)$arguments['id']),
        );
    }

    public function deleteBeforeAction(): Action
    {
        return $this->confirmDeleteAction(
            'deleteBefore',
            'Delete all points before this one?',
            'Every reading recorded before this point will be permanently removed.',
            fn(array $arguments) => $this->performDeleteBefore((int)$arguments['id']),
        );
    }

    public function deleteAfterAction(): Action
    {
        return $this->confirmDeleteAction(
            'deleteAfter',
            'Delete all points after this one?',
            'Every reading recorded after this point will be permanently removed.',
            fn(array $arguments) => $this->performDeleteAfter((int)$arguments['id']),
        );
    }

    public function deleteSelectedAction(): Action
    {
        return $this->confirmDeleteAction(
            'deleteSelected',
            fn(array $arguments): string => 'Delete ' . count($arguments['ids']) . ' selected points?',
            'The selected readings will be permanently removed from the cook chart.',
            fn(array $arguments) => $this->performDeleteSelected(array_map(intval(...), $arguments['ids'])),
        );
    }

    public function addNoteAction(): Action
    {
        return Action::make('addNote')
            ->modalHeading(fn(array $arguments): string => blank($this->cook->readings()->find((int)$arguments['id'])?->note)
                ? 'Add note'
                : 'Edit note')
            ->modalDescription('Add a brief note to this reading. It will appear on the chart and in the tooltip.')
            ->modalSubmitActionLabel('Save')
            ->schema([
                TextInput::make('note')
                    ->label('Note')
                    ->maxLength(255)
                    ->placeholder('e.g. Wrapped in foil'),
            ])
            ->fillForm(function (array $arguments): array {
                $reading = $this->cook->readings()->findOrFail((int)$arguments['id']);

                return [
                    'note' => $reading->note ?? '',
                ];
            })
            ->action(function (array $arguments, array $data): void {
                $this->notifyChartUpdated($this->performSaveNote((int)$arguments['id'], $data['note'] ?? null));
            });
    }
}
?>
<flux:container>
    <x-cook-showcase :cook="$this->cook" editable-chart>
        @if ($this->cook->isOwnedBy(auth()->id()))
            <x-slot:chartToolbar>
                <flux:button href="{{ route('cooks.edit', $this->cook) }}" wire:navigate size="sm" variant="ghost">
                    Edit Details
                </flux:button>
            </x-slot:chartToolbar>
        @endif
    </x-cook-showcase>

    @if ($this->cook->isOwnedBy(auth()->id()))
        <x-filament-actions::modals />
    @endif
</flux:container>
