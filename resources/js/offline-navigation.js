const OFFLINE_NAVIGABLE_PATH = /^\/(?:stats|cooks(?:\/\d+)?)$/;

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
