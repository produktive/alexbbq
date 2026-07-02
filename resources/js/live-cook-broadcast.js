import { syncAppBadgeFromServer } from './app-badge';

let channel = null;
let handler = null;
let echoInstance = null;

async function fetchLiveCookStatus() {
    const response = await fetch('/live/cook-status', {
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
    });

    if (! response.ok) {
        return null;
    }

    return response.json();
}

async function dispatchLiveCookUpdate(payload) {
    const type = payload?.type;
    const cookId = payload?.cookId != null ? Number(payload.cookId) : null;
    const beganAt = payload?.beganAt ?? null;

    if (type === 'reading' && cookId) {
        window.dispatchEvent(new CustomEvent('cook-chart-refresh', { detail: { cookId } }));

        if (beganAt) {
            const status = await fetchLiveCookStatus();

            if (status) {
                window.dispatchEvent(new CustomEvent('live-cook-status', { detail: status }));
                syncAppBadgeFromServer();
            }
        }

        return;
    }

    const status = await fetchLiveCookStatus();

    if (status) {
        window.dispatchEvent(new CustomEvent('live-cook-status', { detail: status }));
        syncAppBadgeFromServer();
    }

    if (type !== 'started' && type !== 'stopped') {
        return;
    }

    const onHome = window.location.pathname === '/' || window.location.pathname === '';

    if (onHome && window.Livewire?.navigate) {
        window.Livewire.navigate(window.location.href);
    }
}

export function resetLiveCookBroadcasts() {
    if (channel && handler) {
        channel.stopListening('.LiveCookUpdated', handler);
    }

    channel = null;
    handler = null;
    echoInstance = null;
}

export function listenForLiveCookBroadcasts() {
    if (typeof window.Echo === 'undefined') {
        return;
    }

    if (channel && handler && echoInstance === window.Echo) {
        return;
    }

    resetLiveCookBroadcasts();

    handler = (payload) => {
        dispatchLiveCookUpdate(payload);
    };

    channel = window.Echo.channel('cooks');
    channel.listen('.LiveCookUpdated', handler);
    echoInstance = window.Echo;
}
