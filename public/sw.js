const CACHE_VERSION = 'v25';
const SHELL_CACHE = `alexbbq-shell-${CACHE_VERSION}`;
const ASSET_CACHE = `alexbbq-assets-${CACHE_VERSION}`;
const COOK_PAGE_CACHE = `alexbbq-cook-pages-${CACHE_VERSION}`;
const OFFLINE_URL = '/offline.html';

const PRECACHE_URLS = [
    OFFLINE_URL,
    '/pwa-icon-512.png',
    '/apple-touch-icon.png',
    '/favicon.ico',
    '/favicon.svg',
    '/favicon-light.svg',
    '/favicon-dark.svg',
];

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

function isStatsPath(pathname) {
    return pathname === '/stats';
}

function isCookChartDataPath(pathname) {
    return /^\/cooks\/\d+\/chart-data$/.test(pathname);
}

function isOfflineCacheablePagePath(pathname) {
    return isCookViewPath(pathname) || isCookListPath(pathname) || isStatsPath(pathname);
}

function isOfflineCacheableRequest(pathname) {
    return isOfflineCacheablePagePath(pathname) || isCookChartDataPath(pathname);
}

function pageCacheRequest(pathname, search = '') {
    return new Request(`${pathname}${search}`, {
        method: 'GET',
        credentials: 'same-origin',
    });
}

function chartDataCacheRequest(pathname) {
    return new Request(pathname, {
        method: 'GET',
        credentials: 'same-origin',
        headers: { Accept: 'application/json' },
    });
}

function cacheRequestForFetch(request) {
    const url = new URL(request.url);

    if (isCookChartDataPath(url.pathname)) {
        return chartDataCacheRequest(url.pathname);
    }

    return pageCacheRequest(url.pathname, url.search);
}

async function readCachedResponse(cache, request) {
    const cacheRequest = cacheRequestForFetch(request);

    return (await cache.match(cacheRequest))
        ?? (await cache.match(request))
        ?? (await cache.match(new URL(request.url).pathname));
}

async function storeCachedResponse(cache, request, response) {
    await cache.put(cacheRequestForFetch(request), response.clone());
}

function shouldBypassCache(pathname) {
    if (isCookChartDataPath(pathname)) {
        return false;
    }

    return pathname.startsWith('/live/')
        || pathname.startsWith('/push-subscriptions')
        || pathname.startsWith('/broadcasting/');
}

function shouldCacheAsset(pathname) {
    return pathname.startsWith('/build/')
        || pathname.startsWith('/pwa-splash/')
        || /\.(?:css|js|png|jpe?g|gif|webp|svg|ico|woff2?|ttf|eot)$/i.test(pathname);
}

function isNavigationRequest(request) {
    return request.mode === 'navigate';
}

async function getOfflinePage() {
    const cached = await caches.match(OFFLINE_URL);

    if (cached) {
        return cached;
    }

    try {
        const response = await fetch(OFFLINE_URL);

        if (response.ok) {
            return response;
        }
    } catch {
        //
    }

    return new Response('You are offline.', {
        status: 503,
        headers: { 'Content-Type': 'text/plain; charset=utf-8' },
    });
}

async function handleNavigate(request, event) {
    try {
        const preloadResponse = await event.preloadResponse;

        if (preloadResponse) {
            return preloadResponse;
        }
    } catch {
        //
    }

    try {
        return await fetch(request, { credentials: 'same-origin' });
    } catch {
        return getOfflinePage();
    }
}

async function warmOfflineCache(path) {
    const cache = await caches.open(COOK_PAGE_CACHE);

    if (isOfflineCacheablePagePath(path)) {
        const pageRequest = pageCacheRequest(path);

        try {
            const response = await fetch(pageRequest);

            if (response.ok) {
                await cache.put(pageRequest, response.clone());
            }
        } catch {
            //
        }

        if (isCookViewPath(path)) {
            const chartRequest = chartDataCacheRequest(`${path}/chart-data`);

            try {
                const chartResponse = await fetch(chartRequest);

                if (chartResponse.ok) {
                    await cache.put(chartRequest, chartResponse.clone());
                }
            } catch {
                //
            }
        }

        return;
    }

    if (isCookChartDataPath(path)) {
        const chartRequest = chartDataCacheRequest(path);

        try {
            const response = await fetch(chartRequest);

            if (response.ok) {
                await cache.put(chartRequest, response.clone());
            }
        } catch {
            //
        }
    }
}

async function handleOfflineCacheableRequest(request) {
    const cache = await caches.open(COOK_PAGE_CACHE);

    try {
        const response = await fetch(request, { credentials: 'same-origin' });

        if (response.ok) {
            await storeCachedResponse(cache, request, response);
        }

        return response;
    } catch (error) {
        const cached = await readCachedResponse(cache, request);

        if (cached) {
            return cached;
        }

        if (isNavigationRequest(request)) {
            return getOfflinePage();
        }

        throw error;
    }
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

self.addEventListener('message', (event) => {
    if (event.data?.type !== 'warm-offline-cache') {
        return;
    }

    const path = event.data.path;

    if (typeof path !== 'string' || ! isOfflineCacheablePagePath(path)) {
        return;
    }

    event.waitUntil(warmOfflineCache(path));
});

self.addEventListener('push', (event) => {
    if (! event.data) {
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
        badge: payload.badge ?? '/pwa-icon-512.png',
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

async function precacheUrl(cache, url) {
    try {
        await cache.add(url);

        return true;
    } catch {
        try {
            const response = await fetch(url, { cache: 'no-store' });

            if (response.ok) {
                await cache.put(url, response);

                return true;
            }
        } catch {
            //
        }
    }

    return false;
}

self.addEventListener('install', (event) => {
    event.waitUntil(
        (async () => {
            const cache = await caches.open(SHELL_CACHE);

            await precacheUrl(cache, OFFLINE_URL);

            await Promise.all(
                PRECACHE_URLS
                    .filter((url) => url !== OFFLINE_URL)
                    .map((url) => precacheUrl(cache, url)),
            );

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

    if (isOfflineCacheableRequest(url.pathname)) {
        event.respondWith(handleOfflineCacheableRequest(request));

        return;
    }

    if (isNavigationRequest(request)) {
        event.respondWith(handleNavigate(request, event));

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
