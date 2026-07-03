import { defineConfig, devices } from '@playwright/test';

const baseURL = process.env.PLAYWRIGHT_BASE_URL ?? 'https://bbq.fiskkarta.com';

export default defineConfig({
    testDir: './tests/e2e',
    fullyParallel: true,
    forbidOnly: !! process.env.CI,
    retries: process.env.CI ? 2 : 0,
    reporter: [['list']],
    use: {
        baseURL,
        trace: 'on-first-retry',
        ignoreHTTPSErrors: true,
        serviceWorkers: 'block',
    },
    projects: [
        {
            name: 'chromium',
            use: { ...devices['Desktop Chrome'] },
        },
        {
            name: 'webkit',
            use: { ...devices['Desktop Safari'] },
        },
    ],
});
