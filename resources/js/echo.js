import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

function reverbConfig() {
    const config = window.__reverbConfig;

    if (! config?.key || ! config?.host) {
        return null;
    }

    const port = Number(config.port) || (config.scheme === 'https' ? 443 : 80);

    return {
        key: config.key,
        wsHost: config.host,
        wsPort: port,
        wssPort: port,
        forceTLS: config.scheme === 'https',
    };
}

export function initEcho() {
    if (window.Echo) {
        return window.Echo;
    }

    const config = reverbConfig();

    if (! config) {
        return null;
    }

    window.Echo = new Echo({
        broadcaster: 'reverb',
        ...config,
        enabledTransports: ['ws', 'wss'],
    });

    return window.Echo;
}

initEcho();

document.addEventListener('livewire:navigated', () => {
    initEcho();
});
