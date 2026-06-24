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

document.addEventListener('livewire:navigated', () => {
    requestAnimationFrame(() => initLiveCookTimers());
});

if ('serviceWorker' in navigator) {
    pushNotifications.registerServiceWorker().catch(() => {});
}
