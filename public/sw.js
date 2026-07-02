const CACHE_VERSION = 'v7';
const SHELL_CACHE = `alexbbq-shell-${CACHE_VERSION}`;
const ASSET_CACHE = `alexbbq-assets-${CACHE_VERSION}`;
const COOK_PAGE_CACHE = `alexbbq-cook-pages-${CACHE_VERSION}`;

const PRECACHE_URLS = [
    '/offline.html',
    '/pwa-icon-512.png',
    '/apple-touch-icon.png',
    '/favicon.ico',
    '/favicon.svg',
];

const NETWORK_TIMEOUT_MS = 4000;

const OFFLINE_FALLBACK_HTML = `<!DOCTYPE html>
<html lang="en" class="dark">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Offline</title>
<style>
body{margin:0;min-height:100dvh;display:grid;place-items:center;padding:1.5rem;font-family:ui-sans-serif,system-ui,sans-serif;background:#1f1f1f;color:#f5f5f5;text-align:center}
p{color:#a3a3a3;line-height:1.5}
a,button{display:inline-block;margin-top:1rem;padding:.75rem 1.25rem;border-radius:9999px;font-weight:600;text-decoration:none;color:#171717;background:#f5f5f5;border:0}
</style>
</head>
<body>
<main>
<h1>You are offline</h1>
<p>The live dashboard needs a network connection.</p>
<a href="/cooks" id="cached-cooks-link" hidden>View cached cooks</a>
<button type="button" onclick="window.location.replace('/')">Try again</button>
</main>
</body>
</html>`;

async function setAppBadge(count = 1) {
    if (! ('setAppBadge' in self.navigator)) {
        return;
    }

    try {
        await self.navigator.setAppBadge(count);
    } catch {
        //
    }
}

async function clearAppBadge() {
    if (! ('clearAppBadge' in self.navigator)) {
        return;
    }

    try {
        await self.navigator.clearAppBadge();
    } catch {
        //
    }
}

function resolveTargetUrl(url) {
    try {
        return new URL(url, self.location.origin).href;
    } catch {
        return `${self.location.origin}/`;
    }
}

function sameDocumentLocation(clientUrl, targetUrl) {
    try {
        const client = new URL(clientUrl);
        const target = new URL(targetUrl);

        return client.origin === target.origin
            && client.pathname === target.pathname
            && client.search === target.search;
    } catch {
        return false;
    }
}

function isCookViewPath(pathname) {
    return /^\/cooks\/\d+$/.test(pathname);
}

function isCookListPath(pathname) {
    return pathname === '/cooks';
}

function isCookChartDataPath(pathname) {
    return /^\/cooks\/\d+\/chart-data$/.test(pathname);
}

function isOfflineCacheablePagePath(pathname) {
    return isCookViewPath(pathname) || isCookListPath(pathname);
}

function isOfflineCacheableRequest(pathname) {
    return isOfflineCacheablePagePath(pathname) || isCookChartDataPath(pathname);
}

function cacheLookupKey(request) {
    const url = new URL(request.url);

    return url.pathname + url.search;
}

async function readCachedResponse(cache, request) {
    const cacheKey = cacheLookupKey(request);
    const cached = await cache.match(cacheKey);

    if (cached) {
        return cached;
    }

    return cache.match(request);
}

function shouldBypassCache(pathname) {
    if (isCookChartDataPath(pathname)) {
        return false;
    }

    return pathname.startsWith('/live/')
        || pathname.endsWith('/chart-data')
        || pathname.startsWith('/push-subscriptions')
        || pathname.startsWith('/broadcasting/');
}

function shouldCacheAsset(pathname) {
    return pathname.startsWith('/build/')
        || /\.(?:css|js|png|jpe?g|gif|webp|svg|ico|woff2?|ttf|eot)$/i.test(pathname);
}

function offlineHtmlResponse() {
    return new Response(OFFLINE_FALLBACK_HTML, {
        headers: {
            'Content-Type': 'text/html; charset=utf-8',
            'Cache-Control': 'no-store',
        },
    });
}

async function getOfflinePage() {
    try {
        const shellCache = await caches.open(SHELL_CACHE);
        const cached = await shellCache.match('/offline.html');

        if (cached) {
            return cached;
        }
    } catch {
        //
    }

    const cached = await caches.match('/offline.html');

    if (cached) {
        return cached;
    }

    return offlineHtmlResponse();
}

async function fetchWithTimeout(request, timeoutMs = NETWORK_TIMEOUT_MS) {
    const controller = new AbortController();
    const timeout = setTimeout(() => controller.abort(), timeoutMs);

    try {
        return await fetch(request, {
            cache: 'no-store',
            credentials: 'same-origin',
            signal: controller.signal,
        });
    } finally {
        clearTimeout(timeout);
    }
}

async function handleOfflineHtmlRequest(request) {
    const shellCache = await caches.open(SHELL_CACHE);
    const cached = await shellCache.match('/offline.html');

    try {
        const response = await fetchWithTimeout(request);

        if (response.ok) {
            await shellCache.put('/offline.html', response.clone());
        }

        return response;
    } catch {
        return cached ?? offlineHtmlResponse();
    }
}

async function handleOfflineCacheableRequest(request) {
    const cache = await caches.open(COOK_PAGE_CACHE);
    const cacheKey = cacheLookupKey(request);

    try {
        const response = await fetchWithTimeout(request);

        if (response.ok) {
            await cache.put(cacheKey, response.clone());
        }

        return response;
    } catch {
        const cached = await readCachedResponse(cache, request);

        if (cached) {
            return cached;
        }

        return Response.error();
    }
}

async function handleNavigate(request) {
    try {
        const response = await fetchWithTimeout(request);

        if (response.ok) {
            return response;
        }
    } catch {
        //
    }

    return getOfflinePage();
}

async function openOrFocusClient(targetUrl) {
    const absoluteUrl = resolveTargetUrl(targetUrl);
    const clients = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });

    for (const client of clients) {
        if (sameDocumentLocation(client.url, absoluteUrl) && 'focus' in client) {
            client.postMessage({ type: 'clear-app-badge' });

            return client.focus();
        }
    }

    const existingClient = clients.find((client) => 'focus' in client);

    if (existingClient) {
        existingClient.postMessage({
            type: 'clear-app-badge',
            navigate: absoluteUrl,
        });

        return existingClient.focus();
    }

    if (self.clients.openWindow) {
        return self.clients.openWindow(absoluteUrl);
    }

    return undefined;
}

self.addEventListener('push', (event) => {
    if (!event.data) {
        return;
    }

    let payload = {};

    try {
        payload = event.data.json();
    } catch {
        payload = {
            title: 'Temperature Alert',
            body: event.data.text(),
        };
    }

    const title = payload.title ?? 'Temperature Alert';
    const options = {
        body: payload.body ?? '',
        icon: payload.icon ?? '/pwa-icon-512.png',
        badge: payload.icon ?? '/pwa-icon-512.png',
        data: payload.data ?? {},
        tag: payload.tag ?? 'temperature-alert',
        renotify: true,
    };

    event.waitUntil(Promise.all([
        self.registration.showNotification(title, options),
        setAppBadge(1),
    ]));
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();

    const data = event.notification.data ?? {};
    const targetUrl = data.url ?? '/';

    event.waitUntil(Promise.all([
        clearAppBadge(),
        openOrFocusClient(targetUrl),
    ]));
});

self.addEventListener('install', (event) => {
    event.waitUntil(
        (async () => {
            const shellCache = await caches.open(SHELL_CACHE);

            await Promise.all(PRECACHE_URLS.map(async (url) => {
                try {
                    await shellCache.add(url);
                } catch {
                    if (url === '/offline.html') {
                        await shellCache.put('/offline.html', offlineHtmlResponse());
                    }
                }
            }));

            await self.skipWaiting();
        })(),
    );
});

self.addEventListener('activate', (event) => {
    const activeCaches = [SHELL_CACHE, ASSET_CACHE, COOK_PAGE_CACHE];

    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(
                keys
                    .filter((key) => ! activeCaches.includes(key))
                    .map((key) => caches.delete(key)),
            ))
            .then(() => self.clients.claim()),
    );
});

self.addEventListener('fetch', (event) => {
    const { request } = event;

    if (request.method !== 'GET') {
        return;
    }

    const url = new URL(request.url);

    if (url.origin !== self.location.origin) {
        return;
    }

    if (shouldBypassCache(url.pathname)) {
        return;
    }

    if (url.pathname === '/offline.html') {
        event.respondWith(handleOfflineHtmlRequest(request));

        return;
    }

    if (isOfflineCacheableRequest(url.pathname)) {
        event.respondWith(handleOfflineCacheableRequest(request));

        return;
    }

    if (request.mode === 'navigate') {
        event.respondWith(handleNavigate(request));

        return;
    }

    if (! shouldCacheAsset(url.pathname)) {
        return;
    }

    event.respondWith(
        caches.open(ASSET_CACHE).then(async (cache) => {
            const cached = await cache.match(request);

            const networkFetch = fetch(request)
                .then((response) => {
                    if (response.ok) {
                        cache.put(request, response.clone());
                    }

                    return response;
                })
                .catch(() => cached);

            return cached ?? networkFetch;
        }),
    );
});
