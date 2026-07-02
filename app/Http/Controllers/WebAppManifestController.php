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
            'start_url' => '/',
            'scope' => '/',
            'id' => '/',
            'display' => 'standalone',
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
            ],
        ])->header('Content-Type', 'application/manifest+json');
    }
}
