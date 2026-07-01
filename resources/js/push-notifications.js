const urlBase64ToUint8Array = (base64String) => {
    const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
    const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
    const rawData = window.atob(base64);
    const outputArray = new Uint8Array(rawData.length);

    for (let index = 0; index < rawData.length; index += 1) {
        outputArray[index] = rawData.charCodeAt(index);
    }

    return outputArray;
};

const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';

const vapidPublicKey = () => document.querySelector('meta[name="vapid-public-key"]')?.content ?? '';

const registerServiceWorker = async () => {
    if (!('serviceWorker' in navigator)) {
        throw new Error('Service workers are not supported in this browser.');
    }

    return navigator.serviceWorker.register('/sw.js');
};

const subscribeToPush = async () => {
    if (!('PushManager' in window)) {
        throw new Error('Push notifications are not supported in this browser.');
    }

    const registration = await registerServiceWorker();
    const permission = await Notification.requestPermission();

    if (permission !== 'granted') {
        throw new Error('Notification permission was not granted.');
    }

    const publicKey = vapidPublicKey();

    if (!publicKey) {
        throw new Error('Push notifications are not configured on the server.');
    }

    const existingSubscription = await registration.pushManager.getSubscription();

    if (existingSubscription) {
        await existingSubscription.unsubscribe();
    }

    const subscription = await registration.pushManager.subscribe({
        userVisibleOnly: true,
        applicationServerKey: urlBase64ToUint8Array(publicKey),
    });

    const payload = subscription.toJSON();

    const response = await fetch('/push-subscriptions', {
        method: 'POST',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
        },
        body: JSON.stringify({
            endpoint: payload.endpoint,
            keys: payload.keys,
            contentEncoding: 'aes128gcm',
        }),
        credentials: 'same-origin',
    });

    if (!response.ok) {
        throw new Error('Unable to save push subscription.');
    }

    return subscription;
};

const unsubscribeFromPush = async () => {
    const registration = await navigator.serviceWorker.getRegistration('/sw.js');
    const subscription = await registration?.pushManager.getSubscription();

    if (!subscription) {
        return;
    }

    const endpoint = subscription.endpoint;

    const response = await fetch('/push-subscriptions', {
        method: 'DELETE',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
        },
        body: JSON.stringify({ endpoint }),
        credentials: 'same-origin',
    });

    if (! response.ok) {
        throw new Error('Unable to remove push subscription.');
    }

    await subscription.unsubscribe();
};

const enable = async () => {
    try {
        await subscribeToPush();
    } catch (error) {
        window.alert(error.message ?? 'Unable to enable push notifications.');
        throw error;
    }
};

const disable = async () => {
    try {
        await unsubscribeFromPush();
    } catch (error) {
        window.alert(error.message ?? 'Unable to disable push notifications.');
        throw error;
    }
};

const pushNotifications = {
    enable,
    disable,
    registerServiceWorker,
};

window.pushNotifications = pushNotifications;

export default pushNotifications;
