const CACHE_VERSION = 'v5';
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

function isOfflineCacheableRequest(request, pathname) {
    if (isCookChartDataPath(pathname)) {
        return true;
    }

    if (! isOfflineCacheablePagePath(pathname)) {
        return false;
    }

    return request.mode === 'navigate'
        || request.headers.get('X-Livewire-Navigate') === '1'
        || request.headers.get('accept')?.includes('text/html');
}

function cacheLookupKey(request) {
    const url = new URL(request.url);

    return url.pathname + url.search;
}

async function readCachedResponse(cache, request) {
    const cached = await cache.match(cacheLookupKey(request));

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

async function handleOfflineCacheableRequest(request) {
    const cache = await caches.open(COOK_PAGE_CACHE);
    const cacheKey = cacheLookupKey(request);

    try {
        const response = await fetch(request, { cache: 'no-store' });

        if (response.ok) {
            await cache.put(cacheKey, response.clone());
        }

        return response;
    } catch {
        const cached = await readCachedResponse(cache, request);

        if (cached) {
            return cached;
        }

        if (request.mode === 'navigate') {
            const offlinePage = await caches.match('/offline.html');

            return offlinePage ?? Response.error();
        }

        return Response.error();
    }
}

async function handleNavigate(request) {
    try {
        const response = await fetch(request, { cache: 'no-store' });

        if (response.ok) {
            return response;
        }
    } catch {
        //
    }

    const offlinePage = await caches.match('/offline.html');

    return offlinePage ?? Response.error();
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

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(SHELL_CACHE)
            .then((cache) => cache.addAll(PRECACHE_URLS))
            .then(() => self.skipWaiting()),
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

    if (isOfflineCacheableRequest(request, url.pathname)) {
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
