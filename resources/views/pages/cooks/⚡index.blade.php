<?php

use App\Models\Cook;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Actions\DeleteAction;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Support\Enums\IconSize;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;
use Illuminate\View\ComponentAttributeBag;
use Livewire\Attributes\Title;
use Livewire\Component;

use function Filament\Support\generate_icon_html;

new
#[Title('Cooks')]
class extends Component implements HasActions, HasForms, HasTable {
    use InteractsWithActions, InteractsWithForms, InteractsWithTable;

    private function cookHasDescriptionText(Cook $record): bool
    {
        return $this->descriptionPreviewText($record) !== '';
    }

    private function descriptionPreviewText(Cook $record): string
    {
        return trim(html_entity_decode(strip_tags($record->getRawOriginal('description') ?? '')));
    }

    private function formatCookTitle(Cook $record): string|Htmlable
    {
        if (! $record->hasDescriptionImages()) {
            return $record->title;
        }

        $icon = generate_icon_html(
            Heroicon::Photo,
            attributes: (new ComponentAttributeBag([
                'class' => 'inline-block align-text-bottom ms-1 text-zinc-400 dark:text-zinc-500',
                'title' => 'Has photos',
                'aria-hidden' => 'true',
            ])),
            size: IconSize::Small,
        );

        return new HtmlString(e($record->title).($icon?->toHtml() ?? ''));
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
                    ->state(function (Cook $record): string|Htmlable {
                        $text = $this->descriptionPreviewText($record);

                        if ($text !== '') {
                            return $text;
                        }

                        return $this->formatCookTitle($record);
                    })
                    ->color(fn (Cook $record): ?string => $this->cookHasDescriptionText($record) ? 'gray' : null)
                    ->extraAttributes(fn (Cook $record): array => $this->cookHasDescriptionText($record) ? [] : ['class' => 'cook-list-title-only'])
                    ->label('Description')
                    ->description(
                        fn (Cook $record): string|Htmlable|null => $this->cookHasDescriptionText($record) ? $this->formatCookTitle($record) : null,
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
                        ->url(fn (Cook $record) => route('cooks.edit', $record))
                        ->visible(fn (Cook $record) => auth()->check() && $record->isOwnedBy(auth()->id())),
                    DeleteAction::make()
                        ->visible(fn (Cook $record) => auth()->check() && $record->isOwnedBy(auth()->id()))
                        ->before(function (DeleteAction $action, Cook $record): void {
                            abort_unless(auth()->check() && $record->isOwnedBy(auth()->id()), 403);
                        }),
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
