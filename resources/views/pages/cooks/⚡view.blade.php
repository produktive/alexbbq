<?php

use App\Models\Cook;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component implements HasActions, HasSchemas {

    use InteractsWithActions;
    use InteractsWithSchemas;

    public Cook $cook;

    public array $selectedReadingIds = [];

    public ?int $selectedReadingId = null;

    public function render()
    {
        return $this->view()->title($this->cook->title);
    }

    private function cleanTemp(?int $value): ?int
    {
        return $value === 0 ? null : $value;
    }

    #[Computed]
    public function chartData(): array
    {
        return $this->buildChartData();
    }

    private function buildChartData(): array
    {
        $readings = $this->cook
            ->readings()
            ->orderBy('time')
            ->get();

        if ($readings->isEmpty()) {
            return [
                'startSecondsOfDay' => 0,
                'food' => [],
                'bbq' => [],
            ];
        }

        $start = $readings->first()->time;

        return [
            // Seconds-since-midnight of the first reading's clock time, taken
            // at face value (no timezone conversion). The frontend uses this
            // plus each point's elapsed-seconds offset to rebuild wall-clock
            // labels via plain arithmetic - never through a JS Date object,
            // which would apply the browser's local timezone.
            'startSecondsOfDay' => $start->hour * 3600 + $start->minute * 60 + $start->second,

            // 'x' is a duration (seconds elapsed since the first reading),
            // not a timestamp - this is what gives the line chart correct,
            // proportional spacing between points instead of treating every
            // reading as evenly spaced.
            //
            // Using a plain timestamp subtraction here rather than
            // diffInSeconds(): Carbon's diffIn*() methods changed their
            // default sign convention between major versions (absolute in
            // Carbon 2, signed in Carbon 3), which silently flipped the
            // direction of every point after the first.
            // ->all() at the end matters here: ->values() alone still
            // returns a Collection, and Collections get wrapped by
            // Livewire's wire-protocol serializer (so they can be hydrated
            // back into Collection instances later). That wrapping is
            // invisible in @js() on first page load - it just JSON-encodes
            // whatever it's given - but it DOES show up when this same
            // array is sent as a dispatch() event payload, since dispatch
            // payloads go through Livewire's normal wire serialization.
            // Plain arrays avoid that wrapping entirely.
            'food' => $readings->map(fn ($r) => [
                'x' => $r->time->getTimestamp() - $start->getTimestamp(),
                'y' => $this->cleanTemp($r->probe_food),
                'id' => $r->id,
                'note' => $r->note,
            ])->values()->all(),

            'bbq' => $readings->map(fn ($r) => [
                'x' => $r->time->getTimestamp() - $start->getTimestamp(),
                'y' => $this->cleanTemp($r->probe_bbq),
                'id' => $r->id,
                'note' => $r->note,
            ])->values()->all(),
        ];
    }

    private function ensureCanModifyReadings(): void
    {
        abort_unless(auth()->check(), 403);
    }

    private function afterReadingsChanged(): array
    {
        $this->cook->syncStartTimeFromReadings();
        $this->cook->refresh();
        $this->cook->unsetRelation('readings');

        return $this->buildChartData();
    }

    private function notifyChartUpdated(?array $chart): void
    {
        if ($chart !== null) {
            // Signal only — chart data is fetched via refreshChartData() so it
            // is JSON-encoded like the initial @js() payload, not wire-wrapped.
            $this->dispatch('cook-chart-updated');
        }
    }

    public function refreshChartData(): array
    {
        return $this->buildChartData();
    }

    private function performDeletePoint(int $id): ?array
    {
        $this->ensureCanModifyReadings();

        if ($this->cook->readings()->whereKey($id)->delete() === 0) {
            return null;
        }

        return $this->afterReadingsChanged();
    }

    private function performDeleteBefore(int $id): ?array
    {
        $this->ensureCanModifyReadings();

        $reading = $this->cook->readings()->findOrFail($id);

        if ($this->cook->readings()->where('time', '<', $reading->time)->delete() === 0) {
            return null;
        }

        return $this->afterReadingsChanged();
    }

    private function performDeleteAfter(int $id): ?array
    {
        $this->ensureCanModifyReadings();

        $reading = $this->cook->readings()->findOrFail($id);

        if ($this->cook->readings()->where('time', '>', $reading->time)->delete() === 0) {
            return null;
        }

        return $this->afterReadingsChanged();
    }

    private function performDeleteSelected(array $ids): ?array
    {
        $this->ensureCanModifyReadings();

        if ($this->cook->readings()->whereIn('id', $ids)->delete() === 0) {
            return null;
        }

        $this->selectedReadingIds = [];

        return $this->afterReadingsChanged();
    }

    private function performSaveNote(int $id, ?string $note): ?array
    {
        $this->ensureCanModifyReadings();

        $reading = $this->cook->readings()->findOrFail($id);

        $reading->note = filled($note) ? $note : null;
        $reading->save();

        $this->cook->unsetRelation('readings');

        return $this->buildChartData();
    }

    public function deletePointAction(): Action
    {
        return Action::make('deletePoint')
            ->requiresConfirmation()
            ->modalHeading('Delete this point?')
            ->modalDescription('This reading will be permanently removed from the cook chart.')
            ->modalSubmitActionLabel('Delete')
            ->color('danger')
            ->action(function (array $arguments): void {
                $this->notifyChartUpdated($this->performDeletePoint((int) $arguments['id']));
            });
    }

    public function deleteBeforeAction(): Action
    {
        return Action::make('deleteBefore')
            ->requiresConfirmation()
            ->modalHeading('Delete all points before this one?')
            ->modalDescription('Every reading recorded before this point will be permanently removed.')
            ->modalSubmitActionLabel('Delete')
            ->color('danger')
            ->action(function (array $arguments): void {
                $this->notifyChartUpdated($this->performDeleteBefore((int) $arguments['id']));
            });
    }

    public function deleteAfterAction(): Action
    {
        return Action::make('deleteAfter')
            ->requiresConfirmation()
            ->modalHeading('Delete all points after this one?')
            ->modalDescription('Every reading recorded after this point will be permanently removed.')
            ->modalSubmitActionLabel('Delete')
            ->color('danger')
            ->action(function (array $arguments): void {
                $this->notifyChartUpdated($this->performDeleteAfter((int) $arguments['id']));
            });
    }

    public function deleteSelectedAction(): Action
    {
        return Action::make('deleteSelected')
            ->requiresConfirmation()
            ->modalHeading(fn (array $arguments): string => 'Delete '.count($arguments['ids']).' selected points?')
            ->modalDescription('The selected readings will be permanently removed from the cook chart.')
            ->modalSubmitActionLabel('Delete')
            ->color('danger')
            ->action(function (array $arguments): void {
                $ids = array_map(intval(...), $arguments['ids']);

                $this->notifyChartUpdated($this->performDeleteSelected($ids));
            });
    }

    public function addNoteAction(): Action
    {
        return Action::make('addNote')
            ->modalHeading(fn (array $arguments): string => blank($this->cook->readings()->find((int) $arguments['id'])?->note)
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
                $reading = $this->cook->readings()->findOrFail((int) $arguments['id']);

                return [
                    'note' => $reading->note ?? '',
                ];
            })
            ->action(function (array $arguments, array $data): void {
                $this->notifyChartUpdated($this->performSaveNote((int) $arguments['id'], $data['note'] ?? null));
            });
    }
}
?>
<flux:container>

    <flux:heading level="1" size="xl" class="my-2">
        {{ $this->cook->title }}
    </flux:heading>

    <flux:text size="md" class="my-2">
        Began {{ $this->cook->getBeganAt()->format('F j, Y \a\t g:i A') }}
    </flux:text>

    <flux:text size="sm" class="my-2">
        {{ $this->cook->getDurationLabel() }}
    </flux:text>

    <div
        wire:ignore
        x-data="window.cookChart(@js($this->chartData), @js(auth()->check()))"
        x-ref="root"
        class="relative h-125"
    >
        <div class="relative h-full">
            <canvas x-ref="canvas" class="block h-full w-full"></canvas>

            {{-- Drag-selection highlight --}}
            <div
                x-show="selection.dragging || selection.active"
                x-bind:style="selection.overlayStyle"
                class="pointer-events-none absolute bg-blue-500/15 border-x border-blue-500"
                x-cloak
            ></div>
        </div>

        {{-- Context menu (authenticated users only) --}}
        @auth
        <div
            x-ref="menu"
            x-show="menu.open"
            @click.outside="menu.open = false"
            class="absolute z-50 min-w-56 bg-white border rounded shadow py-1"
            :style="`left:${menu.x}px;top:${menu.y}px`"
            x-cloak
        >
            <template x-if="!selection.active">
                <div class="flex flex-col">
                    <button type="button" class="flex w-full items-center gap-2 px-3 py-1.5 text-left text-sm hover:bg-gray-100" @click="addNote()">
                        <flux:icon.pencil-square variant="mini" class="size-4 shrink-0 text-gray-500" />
                        <span x-text="menu.pointNote ? 'Edit note' : 'Add note'"></span>
                    </button>

                    <div class="my-1 border-t border-gray-200"></div>

                    <button type="button" class="flex w-full items-center gap-2 px-3 py-1.5 text-left text-sm hover:bg-gray-100" @click="removePoint()">
                        <flux:icon.trash variant="mini" class="size-4 shrink-0 text-gray-500" />
                        Delete this point
                    </button>
                    <button type="button" class="flex w-full items-center gap-2 px-3 py-1.5 text-left text-sm hover:bg-gray-100" @click="removeBefore()">
                        <flux:icon.chevron-double-left variant="mini" class="size-4 shrink-0 text-gray-500" />
                        Delete all before this point
                    </button>
                    <button type="button" class="flex w-full items-center gap-2 px-3 py-1.5 text-left text-sm hover:bg-gray-100" @click="removeAfter()">
                        <flux:icon.chevron-double-right variant="mini" class="size-4 shrink-0 text-gray-500" />
                        Delete all after this point
                    </button>
                </div>
            </template>

            <template x-if="selection.active">
                <div class="flex flex-col">
                    <button type="button" class="flex w-full items-center gap-2 px-3 py-1.5 text-left text-sm hover:bg-gray-100" @click="removeSelected()">
                        <flux:icon.trash variant="mini" class="size-4 shrink-0 text-gray-500" />
                        Delete <span x-text="selection.ids.length"></span> selected points
                    </button>
                    <button type="button" class="flex w-full items-center gap-2 px-3 py-1.5 text-left text-sm hover:bg-gray-100" @click="menu.open = false; clearSelection()">
                        <flux:icon.x-mark variant="mini" class="size-4 shrink-0 text-gray-500" />
                        Clear selection
                    </button>
                </div>
            </template>
        </div>
        @endauth
    </div>

    <div id="cook-description" class="my-2">
        {!! $this->cook->description !!}
    </div>

    @auth
        <div class="flex justify-end">
            <flux:button href="{{ route('cooks.edit', $this->cook) }}">
                Edit Cook
            </flux:button>
        </div>

        <x-filament-actions::modals />
    @endauth

</flux:container>
