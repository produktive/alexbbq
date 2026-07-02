const OFFLINE_NAVIGABLE_PATH = /^\/cooks(?:\/\d+)?$/;

export function isOfflineNavigablePath(pathname) {
    return OFFLINE_NAVIGABLE_PATH.test(pathname);
}

export function warmOfflineCacheForCurrentPage() {
    if (! ('serviceWorker' in navigator)) {
        return;
    }

    const path = window.location.pathname;

    if (! isOfflineNavigablePath(path)) {
        return;
    }

    const message = { type: 'warm-offline-cache', path };

    if (navigator.serviceWorker.controller) {
        navigator.serviceWorker.controller.postMessage(message);

        return;
    }

    navigator.serviceWorker.ready.then((registration) => {
        registration.active?.postMessage(message);
    });
}

export function listenForOfflineNavigation() {
    document.addEventListener('alpine:navigate', (event) => {
        if (navigator.onLine) {
            return;
        }

        try {
            const path = new URL(event.detail.url, window.location.origin).pathname;

            if (! isOfflineNavigablePath(path)) {
                return;
            }

            event.preventDefault();
            window.location.assign(path);
        } catch {
            //
        }
    }, true);

    document.addEventListener('click', (event) => {
        if (navigator.onLine) {
            return;
        }

        const link = event.target.closest('a[href]');

        if (! link || link.target === '_blank' || link.hasAttribute('download')) {
            return;
        }

        let url;

        try {
            url = new URL(link.href, window.location.origin);
        } catch {
            return;
        }

        if (url.origin !== window.location.origin || ! isOfflineNavigablePath(url.pathname)) {
            return;
        }

        event.preventDefault();
        event.stopImmediatePropagation();
        window.location.assign(`${url.pathname}${url.search}${url.hash}`);
    }, true);
}
