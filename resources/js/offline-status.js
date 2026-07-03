function isStandalonePwa() {
    return window.matchMedia('(display-mode: standalone)').matches
        || window.navigator.standalone === true;
}

export function markStandalonePwa() {
    if (! isStandalonePwa()) {
        return;
    }

    document.cookie = 'pwa_mode=1; path=/; max-age=31536000; SameSite=Lax';
}

function ensureOfflineBanner() {
    let banner = document.getElementById('offline-status-banner');

    if (! banner) {
        banner = document.createElement('div');
        banner.id = 'offline-status-banner';
        banner.className = 'offline-status-banner';
        banner.hidden = true;
        banner.setAttribute('role', 'status');
        banner.textContent = 'No internet connection. Some features may be unavailable.';
        document.body.append(banner);
    }

    return banner;
}

function updateOfflineBanner() {
    ensureOfflineBanner().hidden = navigator.onLine;
}

export function listenForOfflineStatus() {
    markStandalonePwa();

    if (! isStandalonePwa()) {
        return;
    }

    window.addEventListener('online', updateOfflineBanner);
    window.addEventListener('offline', updateOfflineBanner);
    updateOfflineBanner();
}
