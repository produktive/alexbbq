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
        '/login/intended',
        '/forgot-password',
        '/reset-password',
    ];

    public static function storeIntendedUrl(?string $redirect, Request $request): void
    {
        $redirect = self::validatedRedirect($redirect);

        if ($redirect === null) {
            return;
        }

        $request->session()->put('url.intended', $redirect);
    }

    public static function pullRedirect(Request $request, string $default = '/'): string
    {
        $redirect = self::validatedRedirect(
            $request->input('redirect') ?? $request->query('redirect'),
        );

        if ($redirect !== null) {
            $request->session()->forget('url.intended');

            return $redirect;
        }

        return $request->session()->pull('url.intended', $default);
    }

    public static function pathFromReferer(Request $request): ?string
    {
        $referer = $request->headers->get('referer');

        if (! is_string($referer) || $referer === '') {
            return null;
        }

        if (! str_starts_with($referer, url('/'))) {
            return null;
        }

        return self::validatedRedirect(self::normalize($referer));
    }

    public static function validatedRedirect(?string $redirect): ?string
    {
        if (! is_string($redirect) || $redirect === '') {
            return null;
        }

        $redirect = self::normalize($redirect);

        if (! self::isValid($redirect) || self::isAuthPage($redirect)) {
            return null;
        }

        return $redirect;
    }

    public static function isValid(string $path): bool
    {
        return str_starts_with($path, '/') && ! str_starts_with($path, '//');
    }

    public static function isAuthPage(string $path): bool
    {
        $path = parse_url($path, PHP_URL_PATH) ?? $path;

        foreach (self::AUTH_PATHS as $authPath) {
            if ($path === $authPath || str_starts_with($path, $authPath.'/')) {
                return true;
            }
        }

        return false;
    }

    public static function normalize(string $path): string
    {
        if (self::isValid($path)) {
            return $path;
        }

        if (str_starts_with($path, url('/'))) {
            $pathOnly = parse_url($path, PHP_URL_PATH) ?: '/';
            $query = parse_url($path, PHP_URL_QUERY);

            return $query ? "{$pathOnly}?{$query}" : $pathOnly;
        }

        return $path;
    }
}
