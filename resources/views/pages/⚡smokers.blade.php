<?php

use App\Models\Smoker;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Livewire\Attributes\Title;
use Livewire\Component;

new
#[Title('Smokers')]
class extends Component implements HasActions, HasForms, HasTable {
    use InteractsWithActions, InteractsWithForms, InteractsWithTable;

    public function addSmokerAction(): CreateAction
    {
        return CreateAction::make('addSmoker')
            ->label('Add New Smoker')
            ->model(Smoker::class)
            ->modalHeading('Add New Smoker')
            ->createAnother(false)
            ->modalWidth('md')
            ->schema([
                TextInput::make('name')
                    ->required(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(Smoker::query()->withTrashed())
            ->emptyStateIcon('heroicon-o-fire')
            ->emptyStateHeading('No Smokers')
            ->emptyStateDescription('Add a new smoker to get started cooking.')
            ->recordActions([
                ActionGroup::make([
                    EditAction::make()
                        ->schema([
                            TextInput::make('name')
                                ->required(),
                        ]),
                    DeleteAction::make()
                        ->label('Archive')
                        ->modalHeading('Archive Smoker?')
                        ->modalDescription('It will be unavailable in new cooks but remain listed here. You can undo this any time.')
                        ->modalSubmitActionLabel('Archive')
                        ->visible(fn (Smoker $record) => ! $record->trashed()),
                    RestoreAction::make()
                        ->requiresConfirmation(false)
                        ->modal(false),
                    Action::make('forceDelete')
                        ->label('Delete Permanently')
                        ->icon('heroicon-o-trash')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->visible(fn (Smoker $record) => ! $record->cooks()->exists())
                        ->action(fn (Smoker $record) => $record->forceDelete()),
                ]),
            ])
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->grow(),
                TextColumn::make('cooks_count')
                    ->label('Cooks')
                    ->counts('cooks')
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->dateTime('F j, Y g:i a')
                    ->sinceTooltip()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('Active')
                    ->state(fn (Smoker $record) => ! $record->trashed())
                    ->boolean()
                    ->toggleable(),
            ])
            ->filters([
                TrashedFilter::make()
                    ->label('Smokers')
                    ->placeholder('Active only')
                    ->falseLabel('Archived only')
                    ->trueLabel('All'),
            ])
            ->modifyQueryUsing(
                fn (Builder $query) => $query->withoutGlobalScopes([
                    SoftDeletingScope::class,
                ])
            )
            ->persistFiltersInSession();
    }
}
?>

<flux:container>
    <flux:heading level="1" size="xl">
        Smokers
    </flux:heading>

    <flux:text>
        Manage your smokers here. You can archive, delete, or restore them.
    </flux:text>

    <div class="max-w-3xl my-6 space-y-4">
        <div class="flex justify-end">
            <flux:button
                variant="primary"
                icon="plus"
                wire:click="mountAction('addSmoker')"
            >
                Add New Smoker
            </flux:button>
        </div>

        {{ $this->table }}
    </div>

    <x-filament-actions::modals />
</flux:container>
