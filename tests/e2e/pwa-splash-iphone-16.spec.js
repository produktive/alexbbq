import { expect, test, devices } from '@playwright/test';
import { loadImageDimensions, resolveStartupImageForDevice } from './pwa-splash.helpers.js';

test.use({
    ...devices['iPhone 16'],
    screen: devices['iPhone 16'].screen,
});

test('iPhone 16 selects the correct startup image', async ({ page, baseURL, browserName }) => {
    test.skip(browserName !== 'webkit', 'Validate startup media queries in WebKit only');

    await page.goto('/');
    await page.locator('link[rel="apple-touch-startup-image"]').first().waitFor({ state: 'attached' });

    const selection = await resolveStartupImageForDevice(page);

    expect(selection.matchCount).toBeGreaterThan(0);
    expect(selection.href).toBe('/pwa-splash/iphone-16-portrait.png');
    expect(selection.media).toContain('393px');

    const dimensions = await loadImageDimensions(page, new URL(selection.href, baseURL).href);

    expect(dimensions.ok).toBe(true);
    expect(dimensions.width).toBe(1179);
    expect(dimensions.height).toBe(2556);
});
