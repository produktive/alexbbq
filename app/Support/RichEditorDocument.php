<?php

namespace App\Support;

use Filament\Forms\Components\RichEditor\RichContentRenderer;

class RichEditorDocument
{
    /**
     * Convert legacy description HTML into RichEditor-compatible HTML.
     */
    public static function sanitizeHtml(?string $html): ?string
    {
        if (blank($html)) {
            return null;
        }

        $html = trim($html);

        if (! self::hasBlockElements($html)) {
            $html = '<p>'.$html.'</p>';
        }

        $editor = RichContentRenderer::make($html)->getEditor();
        $document = json_decode($editor->getJSON(), true);

        if (! is_array($document)) {
            return null;
        }

        $document = self::normalize($document);
        $editor->setContent($document);
        $sanitized = trim($editor->getHTML());

        if (blank($sanitized) || $sanitized === '<p></p>') {
            return null;
        }

        return $sanitized;
    }

    /**
     * Normalize a TipTap document so ProseMirror can map selections reliably.
     *
     * @param  array<string, mixed>  $document
     * @return array<string, mixed>
     */
    public static function normalize(array $document): array
    {
        if (($document['type'] ?? null) !== 'doc' || ! is_array($document['content'] ?? null)) {
            return $document;
        }

        $document['content'] = self::wrapInlineNodes($document['content']);

        $document['content'] = array_map(
            fn (mixed $node): mixed => self::normalizeNode($node),
            $document['content'],
        );

        $document['content'] = self::removeTrailingEmptyBlocks($document['content']);

        if ($document['content'] === []) {
            $document['content'] = [[
                'type' => 'paragraph',
                'content' => [],
            ]];
        }

        return $document;
    }

    protected static function hasBlockElements(string $html): bool
    {
        return (bool) preg_match('/<(p|h[1-6]|ul|ol|li|blockquote|div|table|hr|pre|details)\b/i', $html);
    }

    /**
     * @param  array<int, mixed>  $nodes
     * @return array<int, mixed>
     */
    protected static function wrapInlineNodes(array $nodes): array
    {
        $result = [];
        $inlineBuffer = [];

        $flushInlineBuffer = function () use (&$result, &$inlineBuffer): void {
            if ($inlineBuffer === []) {
                return;
            }

            $result[] = [
                'type' => 'paragraph',
                'content' => $inlineBuffer,
            ];

            $inlineBuffer = [];
        };

        foreach ($nodes as $node) {
            if (! is_array($node)) {
                continue;
            }

            if (self::isInlineNode($node)) {
                $inlineBuffer[] = $node;

                continue;
            }

            $flushInlineBuffer();
            $result[] = $node;
        }

        $flushInlineBuffer();

        return $result;
    }

    /**
     * @param  array<string, mixed>  $node
     */
    protected static function isInlineNode(array $node): bool
    {
        return in_array($node['type'] ?? null, ['text', 'hardBreak'], true);
    }

    /**
     * @return array<string, mixed>|mixed
     */
    protected static function normalizeNode(mixed $node): mixed
    {
        if (! is_array($node)) {
            return $node;
        }

        if (($node['type'] ?? null) === 'listItem' && is_array($node['content'] ?? null)) {
            $firstChild = $node['content'][0] ?? null;

            if (is_array($firstChild) && ($firstChild['type'] ?? null) === 'text') {
                $node['content'] = [[
                    'type' => 'paragraph',
                    'content' => $node['content'],
                ]];
            }
        }

        if (in_array($node['type'] ?? null, ['paragraph', 'heading', 'listItem'], true)
            && ! is_array($node['content'] ?? null)) {
            $node['content'] = [];
        }

        if (is_array($node['content'] ?? null)) {
            $node['content'] = array_map(
                fn (mixed $child): mixed => self::normalizeNode($child),
                $node['content'],
            );
        }

        return $node;
    }

    /**
     * @param  array<int, mixed>  $nodes
     * @return array<int, mixed>
     */
    protected static function removeTrailingEmptyBlocks(array $nodes): array
    {
        while ($nodes !== []) {
            $last = $nodes[array_key_last($nodes)];

            if (! is_array($last) || ! self::isEmptyBlock($last)) {
                break;
            }

            array_pop($nodes);
        }

        return $nodes;
    }

    /**
     * @param  array<string, mixed>  $node
     */
    protected static function isEmptyBlock(array $node): bool
    {
        if (! in_array($node['type'] ?? null, ['paragraph', 'heading'], true)) {
            return false;
        }

        $content = $node['content'] ?? [];

        if (! is_array($content) || $content === []) {
            return true;
        }

        return count($content) === 1
            && ($content[0]['type'] ?? null) === 'hardBreak';
    }
}
