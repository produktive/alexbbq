<?php

namespace App\Support;

class PwaAsset
{
    public static function version(string $path): int
    {
        $fullPath = public_path($path);

        if (! is_file($fullPath)) {
            return 1;
        }

        return filemtime($fullPath) ?: 1;
    }

    public static function url(string $path): string
    {
        return '/'.ltrim($path, '/').'?v='.self::version($path);
    }

    public static function serviceWorkerUrl(): string
    {
        return self::url('sw.js');
    }
}
