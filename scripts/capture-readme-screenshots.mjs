import { chromium, devices } from 'playwright';
import { mkdir } from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const outputDir = path.join(__dirname, '..', 'docs', 'screenshots');

const baseUrl = process.env.APP_URL ?? 'https://alexbbq.test';
const email = process.env.SCREENSHOT_EMAIL ?? 'admin@admin.com';
const password = process.env.SCREENSHOT_PASSWORD ?? 'password';

const finishedCookId = process.env.SCREENSHOT_COOK_ID ?? '738';

const views = [
    { name: 'login', path: '/login', requiresAuth: false },
    { name: 'home', path: '/', requiresAuth: true },
    { name: 'cooks', path: '/cooks', requiresAuth: true },
    { name: 'cook-view', path: `/cooks/${finishedCookId}`, requiresAuth: true },
    { name: 'stats', path: '/stats', requiresAuth: true },
    { name: 'alerts', path: '/alerts', requiresAuth: true },
    { name: 'smokers', path: '/smokers', requiresAuth: true },
];

const profiles = [
    { suffix: 'desktop', viewport: { width: 1440, height: 900 } },
    { suffix: 'mobile', device: devices['iPhone 13'] },
];

async function login(page) {
    await page.goto(`${baseUrl}/login`, { waitUntil: 'networkidle' });
    await page.locator('input[name="email"]').fill(email);
    await page.locator('input[name="password"]').fill(password);
    await page.locator('[data-test="login-button"]').click();
    await page.waitForURL((url) => ! url.pathname.endsWith('/login'), { timeout: 15000 });
}

async function captureProfile(browser, profile) {
    const context = await browser.newContext({
        ignoreHTTPSErrors: true,
        ...(profile.device ?? { viewport: profile.viewport }),
    });

    const page = await context.newPage();
    let loggedIn = false;

    for (const view of views) {
        if (view.requiresAuth && ! loggedIn) {
            await login(page);
            loggedIn = true;
        }

        await page.goto(`${baseUrl}${view.path}`, { waitUntil: 'networkidle' });
        await page.waitForTimeout(750);

        const file = path.join(outputDir, `${view.name}-${profile.suffix}.png`);

        await page.screenshot({ path: file, fullPage: true });
        console.log(`saved ${file}`);
    }

    await context.close();
}

await mkdir(outputDir, { recursive: true });

const browser = await chromium.launch();

for (const profile of profiles) {
    await captureProfile(browser, profile);
}

await browser.close();
