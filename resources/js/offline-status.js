function isStandalonePwa() {
    return window.matchMedia('(display-mode: standalone)').matches
        || window.navigator.standalone === true;
}

function ensureOfflineBanner() {
    let banner = document.getElementById('offline-status-banner');

    if (! banner) {
        banner = document.createElement('div');
        banner.id = 'offline-status-banner';
        banner.className = 'offline-status-banner';
        banner.hidden = true;
        banner.setAttribute('role', 'status');
        banner.textContent = 'Offline — open Cooks for cached pages. Live temps need a connection.';
        document.body.append(banner);
    }

    return banner;
}

function updateOfflineBanner() {
    ensureOfflineBanner().hidden = navigator.onLine;
}

export function listenForOfflineStatus() {
    if (! isStandalonePwa()) {
        return;
    }

    window.addEventListener('online', updateOfflineBanner);
    window.addEventListener('offline', updateOfflineBanner);
    updateOfflineBanner();
}
