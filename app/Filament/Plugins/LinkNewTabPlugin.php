<?php

namespace App\Filament\Plugins;

use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\RichEditor\Actions\LinkAction;
use Filament\Forms\Components\RichEditor\Plugins\Contracts\RichContentPlugin;
use Filament\Forms\Components\RichEditor\RichEditorTool;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class LinkNewTabPlugin implements RichContentPlugin
{
    public static function make(): static
    {
        return app(static::class);
    }

    public function getTipTapPhpExtensions(): array
    {
        return []; // PHP extension for links is already included by Filament's extensions
    }

    public function getTipTapJsExtensions(): array
    {
        return []; // JavaScript for links is already included by Filament's scripts
    }

    public function getEditorTools(): array
    {
        return [
            RichEditorTool::make('linkNewTab')
                ->label(__('filament-forms::components.rich_editor.tools.link'))
                ->action(arguments: '{ url: $getEditor().getAttributes(\'link\')?.href }')
                ->icon(Heroicon::Link)
                ->iconAlias('forms:components.rich-editor.toolbar.link'),
        ];
    }

    public function getEditorActions(): array
    {
        $linkAction = LinkAction::make();

        $schema = $linkAction->getSchema(Schema::make());
        $components = $schema->getComponents();
        $componentsWithoutShouldOpenInNewTabCheckbox = collect($components)
            ->reject(fn ($component) => $component->getName() === 'shouldOpenInNewTab')
            ->all();

        $linkActionFunction = $linkAction->getActionFunction();

        return [
            $linkAction
                ->name('linkNewTab')
                ->schema($componentsWithoutShouldOpenInNewTabCheckbox)
                ->action(function (array $arguments, array $data, RichEditor $component) use ($linkActionFunction) {
                    $data['shouldOpenInNewTab'] = true;
                    $linkActionFunction($arguments, $data, $component);
                }),
        ];
    }
}
