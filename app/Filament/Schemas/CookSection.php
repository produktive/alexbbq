<?php

namespace App\Filament\Schemas;

use App\Filament\Plugins\LinkNewTabPlugin;
use App\Models\Cook;
use App\Models\Smoker;
use App\Support\CookDescriptionImage;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Support\Icons\Heroicon;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

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
                    ->default(Cook::mostRecent()?->smoker_id ?? Smoker::query()->value('id')),

                TextInput::make('title')
                    ->required()
                    ->maxLength(255),

                RichEditor::make('description')
                    ->fileAttachmentsDisk(Cook::DESCRIPTION_ATTACHMENTS_DISK)
                    ->fileAttachmentsDirectory(Cook::DESCRIPTION_ATTACHMENTS_DIRECTORY)
                    ->fileAttachmentsVisibility(Cook::DESCRIPTION_ATTACHMENTS_VISIBILITY)
                    ->fileAttachmentsMaxSize(Cook::DESCRIPTION_ATTACHMENT_MAX_UPLOAD_KB)
                    ->saveUploadedFileAttachmentUsing(
                        fn (TemporaryUploadedFile $file): string => app(CookDescriptionImage::class)->store(
                            $file,
                            Cook::DESCRIPTION_ATTACHMENTS_DIRECTORY,
                            Cook::DESCRIPTION_ATTACHMENTS_DISK,
                            Cook::DESCRIPTION_ATTACHMENTS_VISIBILITY,
                        ),
                    )
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
