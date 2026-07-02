import { chromium } from 'playwright';

const baseUrl = process.env.PWA_TEST_URL ?? 'https://alexbbq.test';

async function waitForServiceWorker(page) {
    await page.waitForFunction(() => {
        return navigator.serviceWorker?.controller !== null;
    }, { timeout: 15000 });
}

async function waitForServiceWorkerVersion(page, version) {
    await page.waitForFunction((expectedVersion) => {
        return fetch('/sw.js', { cache: 'no-store' })
            .then((response) => response.text())
            .then((source) => source.includes(`CACHE_VERSION = '${expectedVersion}'`))
            .catch(() => false);
    }, version, { timeout: 15000 });
}

async function markPwaClient(page) {
    await page.evaluate(async () => {
        document.cookie = 'pwa_mode=1; path=/; max-age=31536000; SameSite=Lax';

        const registration = await navigator.serviceWorker.ready;
        registration.active?.postMessage({ type: 'mark-pwa-client' });
    });

    await page.waitForFunction(async () => {
        const cache = await caches.open('alexbbq-pwa-marker-v11');

        return (await cache.match('/__pwa_client__')) !== undefined;
    }, { timeout: 5000 });
}

async function assertOfflinePage(page) {
    const heading = page.locator('h1');
    await heading.waitFor({ state: 'visible', timeout: 10000 });
    const text = await heading.textContent();

    if (! text?.includes('offline')) {
        throw new Error(`Expected offline page heading, got: ${text}`);
    }
}

const browser = await chromium.launch({ headless: true });
const context = await browser.newContext({
    serviceWorkers: 'allow',
});

try {
    const warmPage = await context.newPage();
    await warmPage.goto(baseUrl, { waitUntil: 'networkidle' });
    await waitForServiceWorker(warmPage);
    await waitForServiceWorkerVersion(warmPage, 'v11');
    await warmPage.reload({ waitUntil: 'networkidle' });
    await waitForServiceWorker(warmPage);
    await markPwaClient(warmPage);

    await context.setOffline(true);

    const coldPage = await context.newPage();
    const response = await coldPage.goto(baseUrl, { waitUntil: 'domcontentloaded' });
    console.log(`Cold start status: ${response?.status()} url: ${coldPage.url()}`);
    await assertOfflinePage(coldPage);

    console.log(`PASS: cold offline start shows offline page at ${baseUrl}`);
} catch (error) {
    console.error(`FAIL: ${error.message}`);
    process.exitCode = 1;
} finally {
    await browser.close();
}
