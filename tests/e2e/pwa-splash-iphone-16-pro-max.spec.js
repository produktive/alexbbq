import { expect, test, devices } from '@playwright/test';
import { loadImageDimensions, resolveStartupImageForDevice } from './pwa-splash.helpers.js';

test.use({
    ...devices['iPhone 16 Pro Max'],
    screen: devices['iPhone 16 Pro Max'].screen,
});

test('iPhone 16 Pro Max selects the correct startup image', async ({ page, baseURL, browserName }) => {
    test.skip(browserName !== 'webkit', 'Validate startup media queries in WebKit only');

    await page.goto('/');
    await page.locator('link[rel="apple-touch-startup-image"]').first().waitFor({ state: 'attached' });

    const selection = await resolveStartupImageForDevice(page);

    expect(selection.matchCount).toBeGreaterThan(0);
    expect(selection.href).toBe('/pwa-splash/iphone-16-pro-max-portrait.png');
    expect(selection.media).toContain('440px');

    const dimensions = await loadImageDimensions(page, new URL(selection.href, baseURL).href);

    expect(dimensions.ok).toBe(true);
    expect(dimensions.width).toBe(1320);
    expect(dimensions.height).toBe(2868);
});
