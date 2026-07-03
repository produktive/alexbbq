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

function normalizeBroadcastPayload(payload) {
    // Reverb/Pusher deliver custom event payloads as JSON strings, not objects.
    if (payload == null) {
        return null;
    }

    if (typeof payload === 'string') {
        try {
            return JSON.parse(payload);
        } catch {
            return null;
        }
    }

    return payload;
}

async function dispatchLiveCookUpdate(payload) {
    const normalized = normalizeBroadcastPayload(payload);

    if (normalized == null) {
        return;
    }

    const type = normalized.type;
    const rawCookId = normalized.cookId ?? normalized.cook_id;
    const cookId = rawCookId != null ? Number(rawCookId) : null;
    const beganAt = normalized.beganAt ?? normalized.began_at ?? null;

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

    if (! window.Livewire?.navigate) {
        return;
    }

    const onHome = window.location.pathname === '/' || window.location.pathname === '';

    if (onHome) {
        window.Livewire.navigate(window.location.href);

        return;
    }

    if (type === 'started') {
        window.Livewire.navigate('/');
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
