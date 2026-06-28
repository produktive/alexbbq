<?php

namespace App\Support;

use App\Models\Cook;
use Illuminate\Support\Facades\Storage;

class CookDescriptionAttachments
{
    /**
     * @return list<string>
     */
    public function extractPaths(?string $content): array
    {
        if (blank($content)) {
            return [];
        }

        $directory = preg_quote(Cook::DESCRIPTION_ATTACHMENTS_DIRECTORY, '#');
        $paths = [];

        if (preg_match_all('#data-id="('.$directory.'/[^"]+)"#', $content, $matches) && filled($matches[1])) {
            $paths = [...$paths, ...$matches[1]];
        }

        if (preg_match_all('#/storage/('.$directory.'/[^"?\s]+)#', $content, $matches) && filled($matches[1])) {
            $paths = [...$paths, ...$matches[1]];
        }

        return array_values(array_unique(array_filter(
            $paths,
            fn (string $path): bool => $this->isAllowedPath($path),
        )));
    }

    public function deleteRemovedPaths(?string $previous, ?string $next): int
    {
        $removed = array_diff(
            $this->extractPaths($previous),
            $this->extractPaths($next),
        );

        return $this->deletePaths($removed);
    }

    public function deleteAllReferenced(?string $content): int
    {
        return $this->deletePaths($this->extractPaths($content));
    }

    /**
     * @param  list<string>  $paths
     */
    public function deletePaths(array $paths): int
    {
        $disk = Storage::disk(Cook::DESCRIPTION_ATTACHMENTS_DISK);
        $deleted = 0;

        foreach (array_unique($paths) as $path) {
            if (! $this->isAllowedPath($path) || ! $disk->exists($path)) {
                continue;
            }

            if ($disk->delete($path)) {
                $deleted++;
            }
        }

        return $deleted;
    }

    public function pruneUnreferenced(): int
    {
        $referenced = [];

        Cook::query()
            ->whereNotNull('description')
            ->select('description')
            ->cursor()
            ->each(function (Cook $cook) use (&$referenced): void {
                foreach ($this->extractPaths($cook->description) as $path) {
                    $referenced[$path] = true;
                }
            });

        $disk = Storage::disk(Cook::DESCRIPTION_ATTACHMENTS_DISK);
        $directory = Cook::DESCRIPTION_ATTACHMENTS_DIRECTORY;

        if (! $disk->exists($directory)) {
            return 0;
        }

        $deleted = 0;

        foreach ($disk->files($directory) as $path) {
            if (isset($referenced[$path]) || ! $this->isAllowedPath($path)) {
                continue;
            }

            if ($disk->delete($path)) {
                $deleted++;
            }
        }

        return $deleted;
    }

    private function isAllowedPath(string $path): bool
    {
        $normalized = str_replace('\\', '/', $path);

        if (str_contains($normalized, '..')) {
            return false;
        }

        return str_starts_with($normalized, Cook::DESCRIPTION_ATTACHMENTS_DIRECTORY.'/');
    }
}
