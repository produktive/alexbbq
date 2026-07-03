import { expect, test } from '@playwright/test';
import {
    EXPECTED_SPLASH_FILES,
    getStartupImages,
    readLocalPngDimensions,
} from './pwa-splash.helpers.js';

const EXPECTED_SPLASH_DIMENSIONS = {
    'iphone-16-portrait.png': { width: 1179, height: 2556 },
    'iphone-16-pro-max-portrait.png': { width: 1320, height: 2868 },
};

test.describe('PWA splash prerequisites', () => {
    test('home page exposes iOS startup images before service worker script', async ({ page }) => {
        await page.goto('/');

        const headHtml = await page.locator('head').innerHTML();
        const scriptIndex = headHtml.indexOf('<script');
        const firstStartupIndex = headHtml.indexOf('apple-touch-startup-image');

        expect(firstStartupIndex).toBeGreaterThan(-1);
        expect(firstStartupIndex).toBeLessThan(scriptIndex);

        await expect(page.locator('meta[name="apple-mobile-web-app-status-bar-style"]')).toHaveAttribute(
            'content',
            'black-translucent',
        );
    });

    test('startup image URLs are stable and every PNG is reachable', async ({ page, request, baseURL }) => {
        await page.goto('/');

        const startupImages = await getStartupImages(page);

        expect(startupImages).toHaveLength(EXPECTED_SPLASH_FILES.length);

        for (const image of startupImages) {
            expect(image.href).not.toContain('?v=');
            expect(image.href).toMatch(/^\/pwa-splash\/[\w-]+\.png$/);

            const response = await request.get(new URL(image.href, baseURL).href);

            expect(response.status(), image.href).toBe(200);
            expect(response.headers()['content-type']).toContain('image/png');
        }
    });

    test('manifest includes standalone display and launch colors', async ({ request, baseURL }) => {
        const response = await request.get(new URL('/manifest.webmanifest', baseURL).href);

        expect(response.status()).toBe(200);

        const manifest = await response.json();

        expect(manifest.display).toBe('standalone');
        expect(manifest.background_color).toBe('#1f1f1f');
        expect(manifest.theme_color).toBe('#1f1f1f');
        expect(manifest.icons.some((icon) => icon.sizes === '512x512')).toBe(true);
    });

    test('local splash PNG dimensions match iOS expectations', async () => {
        for (const [file, dimensions] of Object.entries(EXPECTED_SPLASH_DIMENSIONS)) {
            const { width, height } = readLocalPngDimensions(file);

            expect({ file, width, height }).toEqual({ file, ...dimensions });
        }
    });
});

test('Playwright cannot assert the native iOS launch animation', async () => {
    test.info().annotations.push({
        type: 'limitation',
        description: 'Safari only shows apple-touch-startup-image when launching an installed home-screen web app. Playwright validates HTML/assets, not the home-screen cold-start animation.',
    });
});
