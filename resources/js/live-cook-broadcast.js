let subscribed = false;

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
    const { type, cookId, beganAt } = payload;

    if (type === 'reading' && cookId) {
        window.dispatchEvent(new CustomEvent('cook-chart-refresh', { detail: { cookId } }));

        if (beganAt) {
            const status = await fetchLiveCookStatus();

            if (status) {
                window.dispatchEvent(new CustomEvent('live-cook-status', { detail: status }));
            }
        }

        return;
    }

    const status = await fetchLiveCookStatus();

    if (status) {
        window.dispatchEvent(new CustomEvent('live-cook-status', { detail: status }));
    }

    if (type !== 'started' && type !== 'stopped') {
        return;
    }

    const onHome = window.location.pathname === '/' || window.location.pathname === '';

    if (onHome && window.Livewire?.navigate) {
        window.Livewire.navigate(window.location.href);
    }
}

export function listenForLiveCookBroadcasts() {
    if (subscribed || typeof window.Echo === 'undefined') {
        return;
    }

    subscribed = true;

    window.Echo.channel('cooks').listen('.LiveCookUpdated', (payload) => {
        dispatchLiveCookUpdate(payload);
    });
}
