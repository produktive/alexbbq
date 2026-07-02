import { clientsClaim, skipWaiting } from 'workbox-core';
import { precacheAndRoute, cleanupOutdatedCaches } from 'workbox-precaching';
import { registerRoute, NavigationRoute } from 'workbox-routing';
import { NetworkFirst, NetworkOnly, StaleWhileRevalidate } from 'workbox-strategies';
import { CacheableResponsePlugin } from 'workbox-cacheable-response';

skipWaiting();
clientsClaim();

precacheAndRoute(self.__WB_MANIFEST);
cleanupOutdatedCaches();

const OFFLINE_URL = '/offline.html';
const COOK_PAGES_CACHE = 'cook-pages';
const ASSETS_CACHE = 'assets';

const cacheableResponsePlugin = new CacheableResponsePlugin({
    statuses: [0, 200],
});

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

async function warmOfflineCache(path) {
    const cache = await caches.open(COOK_PAGES_CACHE);

    if (isCookViewPath(path) || isCookListPath(path)) {
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

registerRoute(
    ({ url, request }) => {
        if (request.method !== 'GET') {
            return false;
        }

        if (isCookChartDataPath(url.pathname)) {
            return false;
        }

        return url.pathname.startsWith('/live/')
            || url.pathname.startsWith('/push-subscriptions')
            || url.pathname.startsWith('/broadcasting/');
    },
    new NetworkOnly(),
);

registerRoute(
    ({ url, request }) => {
        return request.method === 'GET' && (
            isOfflineCacheablePagePath(url.pathname) || isCookChartDataPath(url.pathname)
        );
    },
    new NetworkFirst({
        cacheName: COOK_PAGES_CACHE,
        plugins: [cacheableResponsePlugin],
    }),
);

const navigationHandler = async ({ request, event }) => {
    try {
        const preloadResponse = await event.preloadResponse;

        if (preloadResponse) {
            return preloadResponse;
        }
    } catch {
        //
    }

    try {
        return await fetch(request);
    } catch {
        const offlinePage = await caches.match(OFFLINE_URL);

        return offlinePage ?? Response.error();
    }
};

registerRoute(new NavigationRoute(navigationHandler));

registerRoute(
    ({ url, request }) => {
        if (request.method !== 'GET') {
            return false;
        }

        return url.pathname.startsWith('/build/')
            || /\.(?:css|js|png|jpe?g|gif|webp|svg|ico|woff2?|ttf|eot)$/i.test(url.pathname);
    },
    new StaleWhileRevalidate({
        cacheName: ASSETS_CACHE,
        plugins: [cacheableResponsePlugin],
    }),
);

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
