const OFFLINE_NAVIGABLE_PATH = /^\/cooks(?:\/\d+)?$/;

export function isOfflineNavigablePath(pathname) {
    return OFFLINE_NAVIGABLE_PATH.test(pathname);
}

export function listenForOfflineNavigation() {
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
