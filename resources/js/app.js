import cookChart from './cook-chart';
import cookDescriptionGallery from './cook-description-gallery';
import liveCookTimer, { initLiveCookTimers, registerLiveCookTimer } from './live-cook-timer';
import pushNotifications from './push-notifications';

window.cookChart = cookChart;
window.cookDescriptionGallery = cookDescriptionGallery;
window.liveCookTimer = liveCookTimer;
window.pushNotifications = pushNotifications;

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
