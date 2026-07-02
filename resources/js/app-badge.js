export async function setAppBadge(count = 1) {
    if (! ('setAppBadge' in navigator)) {
        return false;
    }

    try {
        await navigator.setAppBadge(count);

        return true;
    } catch {
        return false;
    }
}

export async function clearAppBadge() {
    if (! ('clearAppBadge' in navigator)) {
        return false;
    }

    try {
        await navigator.clearAppBadge();

        return true;
    } catch {
        return false;
    }
}

export async function syncAppBadgeFromServer() {
    try {
        const response = await fetch('/live/alert-badge', {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        });

        if (response.status === 401 || ! response.ok) {
            return;
        }

        const { count } = await response.json();

        if (count > 0) {
            await setAppBadge(count);
        } else {
            await clearAppBadge();
        }
    } catch {
        //
    }
}

export function listenForAppBadgeSync() {
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') {
            syncAppBadgeFromServer();
        }
    });

    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.addEventListener('message', (event) => {
            if (event.data?.type === 'clear-app-badge') {
                clearAppBadge();
            }

            if (event.data?.navigate) {
                const target = new URL(event.data.navigate, window.location.origin);

                if (window.Livewire?.navigate) {
                    window.Livewire.navigate(`${target.pathname}${target.search}`);
                } else {
                    window.location.href = target.href;
                }
            }
        });
    }
}
