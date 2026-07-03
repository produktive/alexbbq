import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import { listenForLiveCookBroadcasts, resetLiveCookBroadcasts } from './live-cook-broadcast';

window.Pusher = Pusher;

function reverbConfig() {
    const config = window.__reverbConfig;

    if (! config?.key || ! config?.host) {
        return null;
    }

    const port = Number(config.port) || (config.scheme === 'https' ? 443 : 80);
    const useTls = config.scheme === 'https';

    return {
        key: config.key,
        wsHost: config.host,
        wsPort: port,
        wssPort: port,
        forceTLS: useTls,
        // Pusher uses the "ws" transport name even for WSS on HTTPS pages.
        enabledTransports: ['ws'],
        disableStats: true,
    };
}

function whenEchoConnected(callback) {
    const connection = window.Echo?.connector?.pusher?.connection;

    if (! connection) {
        return;
    }

    if (connection.state === 'connected') {
        callback();

        return;
    }

    connection.bind('connected', callback);
}

export function initEcho() {
    if (window.Echo) {
        const state = window.Echo.connector?.pusher?.connection?.state;

        if (state === 'connected' || state === 'connecting') {
            whenEchoConnected(listenForLiveCookBroadcasts);

            return window.Echo;
        }

        window.Echo.disconnect();
        window.Echo = undefined;
        resetLiveCookBroadcasts();
    }

    const config = reverbConfig();

    if (! config) {
        return null;
    }

    window.Echo = new Echo({
        broadcaster: 'reverb',
        cluster: 'mt1',
        ...config,
    });

    whenEchoConnected(listenForLiveCookBroadcasts);

    return window.Echo;
}

initEcho();

document.addEventListener('livewire:navigated', () => {
    initEcho();
});
