<?php

namespace App\Support;

use Illuminate\Http\Request;

class AuthRedirect
{
    /**
     * @var list<string>
     */
    private const AUTH_PATHS = [
        '/login',
        '/forgot-password',
        '/reset-password',
    ];

    public static function storeIntendedUrl(?string $redirect, Request $request): void
    {
        if (! is_string($redirect) || $redirect === '') {
            return;
        }

        if (! self::isValid($redirect) || self::isAuthPage($redirect)) {
            return;
        }

        $request->session()->put('url.intended', self::normalize($redirect));
    }

    public static function isValid(string $url): bool
    {
        if (str_starts_with($url, '/') && ! str_starts_with($url, '//')) {
            return true;
        }

        return str_starts_with($url, url('/'));
    }

    public static function isAuthPage(string $url): bool
    {
        $path = parse_url($url, PHP_URL_PATH) ?? $url;

        foreach (self::AUTH_PATHS as $authPath) {
            if ($path === $authPath || str_starts_with($path, $authPath.'/')) {
                return true;
            }
        }

        return false;
    }

    public static function normalize(string $url): string
    {
        if (str_starts_with($url, '/') && ! str_starts_with($url, '//')) {
            return $url;
        }

        $path = parse_url($url, PHP_URL_PATH) ?: '/';
        $query = parse_url($url, PHP_URL_QUERY);

        return $query ? "{$path}?{$query}" : $path;
    }
}
