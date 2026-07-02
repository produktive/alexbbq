import {
    defineConfig
} from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from "@tailwindcss/vite";
import { VitePWA } from 'vite-plugin-pwa';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/js/passkeys.js',
            ],
            refresh: true,
            fonts: [
                bunny('Instrument Sans', {
                    weights: [400, 500, 600],
                }),
            ],
        }),
        tailwindcss(),
        VitePWA({
            strategies: 'injectManifest',
            srcDir: 'resources/js',
            filename: 'sw.js',
            outDir: 'public',
            injectRegister: false,
            manifest: false,
            injectManifest: {
                rollupFormat: 'iife',
                globDirectory: 'public',
                globPatterns: [
                    'offline.html',
                    'pwa-icon-512.png',
                    'apple-touch-icon.png',
                    'favicon.ico',
                    'favicon.svg',
                ],
                globIgnores: [
                    '**/build/**',
                    '**/vendor/**',
                    'sw.js',
                ],
            },
            devOptions: {
                enabled: false,
            },
        }),
    ],
    server: {
        cors: true,
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
