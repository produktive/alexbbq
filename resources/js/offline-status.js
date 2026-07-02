function ensureOfflineBanner() {
    let banner = document.getElementById('offline-status-banner');

    if (! banner) {
        banner = document.createElement('div');
        banner.id = 'offline-status-banner';
        banner.className = 'offline-status-banner';
        banner.hidden = true;
        banner.setAttribute('role', 'status');
        banner.textContent = 'You are offline. Open Cooks to view finished cooks you have opened before. Live temperatures need a connection.';
        document.body.prepend(banner);
    }

    return banner;
}

function updateOfflineBanner() {
    ensureOfflineBanner().hidden = navigator.onLine;
}

export function listenForOfflineStatus() {
    window.addEventListener('online', updateOfflineBanner);
    window.addEventListener('offline', updateOfflineBanner);
    updateOfflineBanner();
}
