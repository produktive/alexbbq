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
use Livewire\Attributes\Title;
use Livewire\Component;

new
#[Title('Cooks')]
class extends Component implements HasActions, HasForms, HasTable {
    use InteractsWithActions, InteractsWithForms, InteractsWithTable;

    private function cookHasDescription(Cook $record): bool
    {
        return filled($record->getRawOriginal('description'));
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(Cook::query()->finished())
            ->columns([
                TextColumn::make('created_at')
                    ->formatStateUsing(fn ($state) => $state->format('M j, Y') . "<br>" . $state->format('g:i a'))
                    ->html()
                    ->sinceTooltip()
                    ->sortable()
                    ->wrap(),
                TextColumn::make('description')
                    ->state(function (Cook $record): string {
                        $text = trim(html_entity_decode(strip_tags($record->renderRichContent('description'))));

                        return $text !== '' ? $text : $record->title;
                    })
                    ->color(fn (Cook $record): ?string => $this->cookHasDescription($record) ? 'gray' : null)
                    ->extraAttributes(fn (Cook $record): array => $this->cookHasDescription($record) ? [] : ['class' => 'cook-list-title-only'])
                    ->label('Description')
                    ->description(
                        fn (Cook $record): ?string => $this->cookHasDescription($record) ? $record->title : null,
                        position: 'above',
                    )
                    ->wrap()
                    ->lineClamp(3)
                    ->grow(),
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
    <flux:heading level="1" size="xl">
        Cooks
    </flux:heading>

    <flux:text>
        View your previous cook charts.
    </flux:text>

    <div class="my-6">
        {{ $this->table }}
    </div>
</flux:container>
