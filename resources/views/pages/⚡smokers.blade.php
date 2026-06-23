<?php

use App\Models\Smoker;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\Attributes\Title;

new
#[Title('Smokers')]
class extends Component implements HasActions, HasForms, HasTable {
    use InteractsWithActions, InteractsWithForms, InteractsWithTable;

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    public function smokers(): Collection
    {
        return Smoker::all();
    }

    private function hasRecords(): bool
    {
        return $this->getFilteredTableQuery()->exists();
    }

    private function createForm($formName): CreateAction
    {
        return CreateAction::make($formName)
            ->label('Add New Smoker')
            ->modalHeading('Add New Smoker')
            ->createAnother(false)
            ->modalWidth('md')
            ->schema([
                TextInput::make('name')
                    ->required()
            ]);
    }

    public function table(Table $table): Table
    {
        // Table
        return $table
            ->query(Smoker::query()->withTrashed())
            ->emptyStateIcon('heroicon-o-fire')
            ->emptyStateHeading('No Smokers')
            ->emptyStateDescription('Add a new smoker to get started cooking.')
            ->emptyStateActions([
                $this->createForm('add_smoker_empty')
            ])

            // Toolbar
            ->toolbarActions([
                $this->createForm('add_smoker_toolbar')
                    ->hidden(fn() => !$this->hasRecords())
            ])

            // Row-level records
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make()
                        ->schema([
                            TextEntry::make('name'),
                            TextEntry::make('cooks_count')
                                ->counts('cooks'),
                            TextEntry::make('created_at')
                                ->since()
                        ]),
                    EditAction::make()
                        ->schema([
                            TextInput::make('name')
                                ->required()
                        ]),
                    DeleteAction::make()
                        ->label('Archive')
                        ->modalHeading('Archive Smoker?')
                        ->modalDescription('It will be unavailable in new cooks but remain listed here. You can undo this any time.')
                        ->modalSubmitActionLabel('Archive')
                        ->visible(fn(Smoker $record) => !$record->trashed()),
                    RestoreAction::make()
                        ->requiresConfirmation(false)
                        ->modal(false),
                    Action::make('forceDelete')
                        ->label('Delete Permanently')
                        ->icon('heroicon-o-trash')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->visible(fn(Smoker $record) => !$record->cooks()->exists())
                        ->action(fn(Smoker $record) => $record->forceDelete())
                ])
            ])

            // Column schema
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->grow(),
                TextColumn::make('cooks_count')
                    ->label('Cooks')
                    ->counts('cooks')
                    ->toggleable(),
                IconColumn::make('Active')
                    ->state(fn(Smoker $record) => !$record->trashed())
                    ->boolean()
                    ->toggleable()
            ])

            // Filters
            ->filters([
                TrashedFilter::make()
                    ->label('Smokers')
                    ->placeholder('Active only')
                    ->falseLabel('Archived only')
                    ->trueLabel('All'),
            ])
            ->modifyQueryUsing(
                fn(Builder $query) => $query->withoutGlobalScopes([
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

    <div class="max-w-3xl my-6">
        {{ $this->table }}
    </div>
</flux:container>
