import {
    defineConfig,
    loadEnv,
} from 'vite';
import { configDefaults } from 'vitest/config';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from "@tailwindcss/vite";

function appHostname(env) {
    try {
        return new URL(env.APP_URL ?? 'http://127.0.0.1:8000').hostname;
    } catch {
        return '127.0.0.1';
    }
}

export default defineConfig(({ mode }) => {
    const env = loadEnv(mode, process.cwd(), '');
    const hostname = appHostname(env);
    // Herd/Valet TLS auto-detection only applies when APP_URL uses a *.test domain.
    const useHerdTls = hostname.endsWith('.test');

    return {
        test: {
            environment: 'node',
            include: ['resources/js/**/*.test.js'],
            exclude: [...configDefaults.exclude],
        },
        plugins: [
            laravel({
                input: [
                    'resources/css/app.css',
                    'resources/js/app.js',
                    'resources/js/passkeys.js',
                ],
                refresh: true,
                detectTls: useHerdTls,
                fonts: [
                    bunny('Instrument Sans', {
                        weights: [400, 500, 600],
                    }),
                ],
            }),
            tailwindcss(),
        ],
        server: {
            cors: true,
            ...(useHerdTls ? {} : {
                host: '127.0.0.1',
                hmr: { host: '127.0.0.1' },
            }),
            watch: {
                ignored: ['**/storage/framework/views/**'],
            },
        },
    };
});
