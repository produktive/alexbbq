<?php

namespace App\Filament\Schemas;

use App\Filament\Plugins\LinkNewTabPlugin;
use App\Models\Cook;
use App\Models\Smoker;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Support\Icons\Heroicon;

class CookSection
{
    public static function make($collapse = false): Section
    {
        return Section::make('Cook Details')
            ->description('Choose your smoker and enter a title and description.')
            ->icon(Heroicon::PencilSquare)
            ->schema([
                Select::make('smoker_id')
                    ->label('Smoker')
                    ->required()
                    ->options(
                        Smoker::query()
                            ->pluck('name', 'smokers.id')
                            ->toArray()
                    )
                    ->default(Cook::latest()->first()?->smoker()->id ?? Smoker::first()->id),

                TextInput::make('title')
                    ->required()
                    ->maxLength(255),

                RichEditor::make('description')
                    ->plugins([
                        LinkNewTabPlugin::make(),
                    ])
                    ->toolbarButtons([
                        ['bold', 'italic', 'underline', 'linkNewTab'],
                        [],
                        [],
                        ['bulletList', 'orderedList'],
                        ['attachFiles'],
                        ['undo', 'redo'],
                    ]),
            ]);
    }
}
