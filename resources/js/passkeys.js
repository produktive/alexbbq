import { Passkeys } from '@laravel/passkeys';
import { startAuthentication } from '@simplewebauthn/browser';

function readAuthRedirect() {
    const input = document.querySelector('[data-auth-redirect]');

    if (input instanceof HTMLInputElement && input.value !== '') {
        return input.value;
    }

    const query = new URLSearchParams(window.location.search).get('redirect');

    if (query && query.startsWith('/') && ! query.startsWith('//')) {
        return query;
    }

    return null;
}

function csrfHeaders() {
    const meta = document.querySelector('meta[name="csrf-token"]');

    if (meta instanceof HTMLMetaElement && meta.content !== '') {
        return { 'X-CSRF-TOKEN': meta.content };
    }

    const prefix = 'XSRF-TOKEN=';
    const cookie = document.cookie.split('; ').find((value) => value.startsWith(prefix));

    if (! cookie) {
        return {};
    }

    return { 'X-XSRF-TOKEN': decodeURIComponent(cookie.slice(prefix.length)) };
}

async function readJson(response) {
    if (response.ok) {
        return response.json();
    }

    let message = `Request failed with status ${response.status}`;

    try {
        const data = await response.json();

        if (typeof data.message === 'string' && data.message !== '') {
            message = data.message;
        }
    } catch {
        //
    }

    throw new Error(message);
}

async function getJson(url) {
    return readJson(await fetch(url, {
        method: 'GET',
        headers: {
            Accept: 'application/json',
            ...csrfHeaders(),
        },
        credentials: 'same-origin',
    }));
}

async function postJson(url, body) {
    return readJson(await fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            ...csrfHeaders(),
        },
        credentials: 'same-origin',
        body: JSON.stringify(body),
    }));
}

Passkeys.verify = async (options = {}) => {
    if (! Passkeys.isSupported()) {
        throw new Error('Passkeys are not supported in this browser.');
    }

    Passkeys.cancel();

    const routes = {
        options: options.routes?.options ?? '/passkeys/login/options',
        submit: options.routes?.submit ?? '/passkeys/login',
    };

    const redirect = readAuthRedirect();
    const optionsUrl = redirect
        ? `${routes.options}?${new URLSearchParams({ redirect }).toString()}`
        : routes.options;

    const { options: optionsJSON } = await getJson(optionsUrl);

    let credential;

    try {
        credential = await startAuthentication({ optionsJSON });
    } catch (error) {
        if (error instanceof Error && error.name === 'NotAllowedError') {
            const cancelled = new Error('The passkey operation was cancelled.');
            cancelled.name = 'UserCancelledError';
            throw cancelled;
        }

        throw error;
    }

    const body = { credential };

    if (redirect) {
        body.redirect = redirect;
    }

    return postJson(routes.submit, body);
};

window.Passkeys = Passkeys;
window.dispatchEvent(new CustomEvent('passkeys:ready'));
