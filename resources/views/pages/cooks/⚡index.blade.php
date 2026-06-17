<?php

use App\Models\Cook;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Actions\DeleteAction;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new
#[Title('List Cooks')]
class extends Component implements HasActions, HasForms, HasTable {
    use InteractsWithActions, InteractsWithForms, InteractsWithTable;

    public function table(Table $table): Table
    {
        return $table
            ->query(Cook::query())
            ->columns([
                TextColumn::make('created_at')
                    ->formatStateUsing(fn ($state) => $state->format('M j, Y') . "<br>" . $state->format('g:i a'))
                    ->html()
                    ->sinceTooltip()
                    ->sortable()
                    ->wrap(),
                TextColumn::make('description')
                    ->formatStateUsing(fn(string $state): string => html_entity_decode(strip_tags($state)))
                    ->color('gray')
                    ->label('Description')
                    ->description(fn(Cook $record): string => $record->title, position: 'above')
                    ->wrap()
                    ->lineClamp(2),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                ActionGroup::make([
                    Action::make('Edit')
                        ->icon(Heroicon::PencilSquare)
                        ->url(fn(Cook $record) => route('cooks.edit', $record))
                        ->visible(fn() => auth()->check()),
                    DeleteAction::make()
                        ->visible(fn() => auth()->check())
                ])
            ])
            ->recordUrl(fn(Cook $record): string => route('cooks.view', ['cook' => $record]))
            ->searchable(['id', 'title', 'description']);
    }
}
?>

<flux:container>
    <flux:heading level="1" size="xl">Cooks</flux:heading>

    <div class="my-6">
        {{ $this->table }}
    </div>
</flux:container>
