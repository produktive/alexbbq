<?php

use App\Models\Cook;
use App\Support\CookChartData;
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

    public function render()
    {
        return $this->view()->title($this->cook->title);
    }

    #[Computed]
    public function chartData(): array
    {
        return CookChartData::fromCook($this->cook);
    }

    public function refreshChartData(): array
    {
        unset($this->chartData);

        return $this->chartData;
    }

    private function ensureCanModifyReadings(): void
    {
        abort_unless(auth()->check(), 403);
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
                @click.outside="menu.open = false; menu.positioned = false"
                class="absolute z-50 min-w-56 rounded border border-zinc-200 bg-white py-1 text-zinc-900 shadow dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100"
                :class="{ invisible: !menu.positioned }"
                :style="`left:${menu.x}px;top:${menu.y}px`"
                x-cloak
            >
                <div x-show="!selection.active" class="flex flex-col">
                    <button type="button"
                            class="flex w-full items-center gap-2 px-3 py-1.5 text-left text-sm hover:bg-zinc-100 dark:hover:bg-zinc-800"
                            @click="mountPointAction('addNote')">
                        <flux:icon.pencil-square variant="mini" class="size-4 shrink-0 text-zinc-500 dark:text-zinc-400"/>
                        <span x-text="menu.pointNote ? 'Edit note' : 'Add note'"></span>
                    </button>

                    <div class="my-1 border-t border-zinc-200 dark:border-zinc-700"></div>

                    <button type="button"
                            class="flex w-full items-center gap-2 px-3 py-1.5 text-left text-sm hover:bg-zinc-100 dark:hover:bg-zinc-800"
                            @click="mountPointAction('deletePoint')">
                        <flux:icon.trash variant="mini" class="size-4 shrink-0 text-zinc-500 dark:text-zinc-400"/>
                        Delete this point
                    </button>
                    <button type="button"
                            x-show="menu.canDeleteBefore"
                            class="flex w-full items-center gap-2 px-3 py-1.5 text-left text-sm hover:bg-zinc-100 dark:hover:bg-zinc-800"
                            @click="mountPointAction('deleteBefore')">
                        <flux:icon.chevron-double-left variant="mini" class="size-4 shrink-0 text-zinc-500 dark:text-zinc-400"/>
                        Delete all before this point
                    </button>
                    <button type="button"
                            x-show="menu.canDeleteAfter"
                            class="flex w-full items-center gap-2 px-3 py-1.5 text-left text-sm hover:bg-zinc-100 dark:hover:bg-zinc-800"
                            @click="mountPointAction('deleteAfter')">
                        <flux:icon.chevron-double-right variant="mini" class="size-4 shrink-0 text-zinc-500 dark:text-zinc-400"/>
                        Delete all after this point
                    </button>
                </div>

                <div x-show="selection.active" class="flex flex-col">
                    <button type="button"
                            class="flex w-full items-center gap-2 px-3 py-1.5 text-left text-sm hover:bg-zinc-100 dark:hover:bg-zinc-800"
                            @click="removeSelected()">
                        <flux:icon.trash variant="mini" class="size-4 shrink-0 text-zinc-500 dark:text-zinc-400"/>
                        <span x-text="`Delete ${selection.ids.length} selected points`"></span>
                    </button>
                    <button type="button"
                            class="flex w-full items-center gap-2 px-3 py-1.5 text-left text-sm hover:bg-zinc-100 dark:hover:bg-zinc-800"
                            @click="menu.open = false; menu.positioned = false; clearSelection()">
                        <flux:icon.x-mark variant="mini" class="size-4 shrink-0 text-zinc-500 dark:text-zinc-400"/>
                        Clear selection
                    </button>
                </div>
            </div>
        @endauth
    </div>

    <div x-data="cookDescriptionGallery()" class="my-6">
        <article
            id="cook-description"
            x-ref="content"
            class="prose cook-description dark:prose-invert"
            :class="{ 'is-processed': processed }"
        >
            {!! $this->cook->renderRichContent('description') !!}
        </article>

        <template x-teleport="body">
            <div
                x-show="lightboxOpen"
                x-cloak
                x-transition.opacity
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/85 p-4"
                @keydown.escape.window="closeLightbox()"
                @keydown.arrow-right.window="lightboxOpen && next()"
                @keydown.arrow-left.window="lightboxOpen && prev()"
            >
                <button
                    type="button"
                    class="absolute inset-0 cursor-default"
                    aria-label="Close lightbox"
                    @click="closeLightbox()"
                ></button>

                <button
                    type="button"
                    class="absolute end-4 top-4 z-10 rounded-full bg-black/50 p-2 text-white hover:bg-black/70"
                    aria-label="Close"
                    @click="closeLightbox()"
                >
                    <flux:icon.x-mark variant="mini" class="size-5"/>
                </button>

                <template x-if="hasMultiple()">
                    <button
                        type="button"
                        class="absolute start-2 top-1/2 z-10 -translate-y-1/2 rounded-full bg-black/50 p-2 text-white hover:bg-black/70 sm:start-4"
                        aria-label="Previous image"
                        @click.stop="prev()"
                    >
                        <flux:icon.chevron-left variant="mini" class="size-6"/>
                    </button>
                </template>

                <img
                    :src="lightboxSrc()"
                    :alt="lightboxAlt()"
                    class="relative z-[1] max-h-[90vh] max-w-[90vw] rounded-lg object-contain shadow-2xl"
                    @click.stop
                >

                <template x-if="hasMultiple()">
                    <button
                        type="button"
                        class="absolute end-2 top-1/2 z-10 -translate-y-1/2 rounded-full bg-black/50 p-2 text-white hover:bg-black/70 sm:end-4"
                        aria-label="Next image"
                        @click.stop="next()"
                    >
                        <flux:icon.chevron-right variant="mini" class="size-6"/>
                    </button>
                </template>
            </div>
        </template>
    </div>

    @auth
        <div class="flex justify-end">
            <flux:button href="{{ route('cooks.edit', $this->cook) }}">
                Edit Cook
            </flux:button>
        </div>

        <x-filament-actions::modals/>
    @endauth

</flux:container>
