const CACHE_VERSION = 'v11';
const SHELL_CACHE = `alexbbq-shell-${CACHE_VERSION}`;
const ASSET_CACHE = `alexbbq-assets-${CACHE_VERSION}`;
const COOK_PAGE_CACHE = `alexbbq-cook-pages-${CACHE_VERSION}`;
const PWA_MARKER_CACHE = `alexbbq-pwa-marker-${CACHE_VERSION}`;
const PWA_MARKER_URL = '/__pwa_client__';

const PRECACHE_URLS = [
    '/offline.html',
    '/pwa-icon-512.png',
    '/apple-touch-icon.png',
    '/favicon.ico',
    '/favicon.svg',
];

const OFFLINE_FALLBACK_HTML = `<!DOCTYPE html>
<html lang="en" class="dark">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="color-scheme" content="dark">
<meta name="theme-color" content="#1f1f1f">
<title>Offline</title>
<style>
:root{color-scheme:dark}*{box-sizing:border-box}body{margin:0;min-height:100dvh;display:grid;place-items:center;padding:1.5rem;font-family:ui-sans-serif,system-ui,sans-serif;background:#1f1f1f;color:#f5f5f5}main{width:min(100%,24rem);text-align:center}img{width:5rem;height:5rem;margin:0 auto 1.5rem;border-radius:1rem}h1{margin:0 0 .75rem;font-size:1.5rem;font-weight:600}p{margin:0 0 1.5rem;line-height:1.5;color:#a3a3a3}#retry-status{min-height:1.25rem;margin:0 0 1rem;font-size:.875rem;color:#fbbf24}.actions{display:flex;flex-direction:column;gap:.75rem;align-items:center}button,.button-link{display:inline-flex;align-items:center;justify-content:center;border:0;border-radius:9999px;padding:.75rem 1.25rem;font:inherit;font-weight:600;text-decoration:none;cursor:pointer}button{color:#171717;background:#f5f5f5}.button-link{color:#f5f5f5;background:transparent;border:1px solid #525252}#cached-cooks-link[hidden]{display:none}button:disabled{opacity:.6;cursor:wait}
</style>
</head>
<body>
<main>
<img src="/pwa-icon-512.png" alt="" width="80" height="80">
<h1>You are offline</h1>
<p>The live dashboard needs a network connection. Finished cook pages you opened while online can still be viewed from the Cooks list.</p>
<p id="retry-status" aria-live="polite"></p>
<div class="actions">
<a id="cached-cooks-link" class="button-link" href="/cooks" hidden>View cached cooks</a>
<button id="retry-button" type="button">Try again</button>
</div>
</main>
<script>
(function(){const b=document.getElementById("retry-button"),s=document.getElementById("retry-status"),l=document.getElementById("cached-cooks-link");async function c(){if(!("caches"in window))return!1;for(const n of await caches.keys()){if(!n.includes("cook-pages"))continue;if(await(await caches.open(n)).match("/cooks"))return!0}return!1}(async function(){if(await c())l.hidden=!1})();async function o(){try{const r=await fetch("/live/cook-status",{cache:"no-store",credentials:"same-origin",headers:{Accept:"application/json"}});return r.ok}catch{return!1}}b.addEventListener("click",async function(){b.disabled=!0;s.textContent="Checking connection…";if(!await o()){b.disabled=!1;s.textContent="Still offline. Check your connection and try again.";return}window.location.replace("/?_retry="+Date.now())});window.addEventListener("online",function(){s.textContent="Connection restored. Tap try again."})})();
</script>
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
        || pathname.endsWith('/chart-data')
        || pathname.startsWith('/push-subscriptions')
        || pathname.startsWith('/broadcasting/');
}

function shouldCacheAsset(pathname) {
    return pathname.startsWith('/build/')
        || /\.(?:css|js|png|jpe?g|gif|webp|svg|ico|woff2?|ttf|eot)$/i.test(pathname);
}

function isDocumentNavigation(request) {
    if (request.mode === 'navigate') {
        return true;
    }

    return request.method === 'GET' && request.destination === 'document';
}

function hasPwaCookie(request) {
    const cookies = request.headers.get('Cookie') ?? '';

    return /(?:^|;\s*)pwa_mode=1(?:;|$)/.test(cookies);
}

async function isPwaClientMarked() {
    try {
        const cache = await caches.open(PWA_MARKER_CACHE);

        return (await cache.match(PWA_MARKER_URL)) !== undefined;
    } catch {
        return false;
    }
}

async function markPwaClient() {
    const cache = await caches.open(PWA_MARKER_CACHE);

    await cache.put(PWA_MARKER_URL, new Response('1', {
        headers: { 'Content-Type': 'text/plain' },
    }));
}

async function isPwaClient(request) {
    return hasPwaCookie(request) || await isPwaClientMarked();
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

    try {
        const response = await fetch('/offline.html', { credentials: 'same-origin' });

        if (response.ok) {
            return response;
        }
    } catch {
        //
    }

    return offlineHtmlResponse();
}

async function handlePwaNavigate(request) {
    try {
        return await fetch(request, { credentials: 'same-origin' });
    } catch {
        return getOfflinePage();
    }
}

async function handleDocumentNavigation(request) {
    if (! await isPwaClient(request)) {
        return fetch(request, { credentials: 'same-origin' });
    }

    return handlePwaNavigate(request);
}

async function warmOfflineCache(path) {
    const cache = await caches.open(COOK_PAGE_CACHE);

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
    if (event.data?.type === 'mark-pwa-client') {
        event.waitUntil(markPwaClient());

        return;
    }

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
        (async () => {
            const cache = await caches.open(SHELL_CACHE);

            await Promise.all(PRECACHE_URLS.map(async (url) => {
                try {
                    await cache.add(url);
                } catch {
                    try {
                        const response = await fetch(url);

                        if (response.ok) {
                            await cache.put(url, response);
                        }
                    } catch {
                        if (url === '/offline.html') {
                            await cache.put('/offline.html', offlineHtmlResponse());
                        }
                    }
                }
            }));

            await self.skipWaiting();
        })(),
    );
});

self.addEventListener('activate', (event) => {
    const activeCaches = [SHELL_CACHE, ASSET_CACHE, COOK_PAGE_CACHE, PWA_MARKER_CACHE];

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

    if (isDocumentNavigation(request)) {
        if (hasPwaCookie(request)) {
            event.waitUntil(markPwaClient());
        }

        event.respondWith(handleDocumentNavigation(request));

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
