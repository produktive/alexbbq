<?php

namespace App\Http\Controllers;

use App\Support\PwaAsset;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class WebAppManifestController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $name = config('app.name');

        return response()->json([
            'name' => $name,
            'short_name' => config('pwa.short_name') ?? Str::limit($name, 12, ''),
            'description' => config('pwa.description'),
            'lang' => str_replace('_', '-', config('pwa.lang') ?? app()->getLocale()),
            'dir' => config('pwa.dir', 'ltr'),
            'start_url' => '/',
            'scope' => '/',
            'id' => '/',
            'display' => 'standalone',
            'orientation' => config('pwa.orientation', 'any'),
            'prefer_related_applications' => (bool) config('pwa.prefer_related_applications', false),
            'categories' => array_values(config('pwa.categories', [])),
            'background_color' => config('pwa.background_color'),
            'theme_color' => config('pwa.theme_color'),
            'icons' => [
                [
                    'src' => PwaAsset::url('apple-touch-icon.png'),
                    'sizes' => '180x180',
                    'type' => 'image/png',
                    'purpose' => 'any',
                ],
                [
                    'src' => PwaAsset::url('pwa-icon-192.png'),
                    'sizes' => '192x192',
                    'type' => 'image/png',
                    'purpose' => 'any',
                ],
                [
                    'src' => PwaAsset::url('pwa-icon-512.png'),
                    'sizes' => '512x512',
                    'type' => 'image/png',
                    'purpose' => 'any',
                ],
                [
                    'src' => PwaAsset::url('pwa-icon-512-maskable.png'),
                    'sizes' => '512x512',
                    'type' => 'image/png',
                    'purpose' => 'maskable',
                ],
            ],
            'shortcuts' => $this->shortcuts(),
        ])->header('Content-Type', 'application/manifest+json');
    }

    /**
     * @return list<array{name: string, short_name: string, url: string, icons: list<array{src: string, sizes: string, type: string}>}>
     */
    private function shortcuts(): array
    {
        $icon = [
            [
                'src' => PwaAsset::url('pwa-icon-192.png'),
                'sizes' => '192x192',
                'type' => 'image/png',
            ],
        ];

        return collect(config('pwa.shortcuts', []))
            ->map(fn (array $shortcut) => [
                'name' => $shortcut['name'],
                'short_name' => $shortcut['short_name'],
                'url' => route($shortcut['route'], absolute: false),
                'icons' => $icon,
            ])
            ->values()
            ->all();
    }
}
