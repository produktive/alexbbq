import pushNotifications from './push-notifications';
import { listenForAppBadgeSync, syncAppBadgeFromServer } from './app-badge';
import './cook-description-image-upload';
import './echo';
import cookChart from './cook-chart';
import cookDescriptionGallery from './cook-description-gallery';
import liveCookTimer, { initLiveCookTimers, registerLiveCookTimer } from './live-cook-timer';

window.cookChart = cookChart;
window.cookDescriptionGallery = cookDescriptionGallery;
window.liveCookTimer = liveCookTimer;

if (window.Alpine) {
    registerLiveCookTimer(window.Alpine);
}

document.addEventListener('alpine:init', () => {
    registerLiveCookTimer(window.Alpine);
});

function initFilamentAlpineComponents() {
    const root = document.querySelector('[data-flux-main]');

    if (! root || ! window.Alpine) {
        return;
    }

    window.Alpine.initTree(root);
}

document.addEventListener('livewire:navigated', () => {
    requestAnimationFrame(() => {
        initFilamentAlpineComponents();
        initLiveCookTimers();
    });
});

if ('serviceWorker' in navigator) {
    pushNotifications.registerServiceWorker().catch(() => {});
}

listenForAppBadgeSync();
syncAppBadgeFromServer();

document.addEventListener('livewire:navigated', () => {
    syncAppBadgeFromServer();
});
