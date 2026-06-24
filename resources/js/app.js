import cookChart from './cook-chart';
import cookDescriptionGallery from './cook-description-gallery';
import liveCookTimer from './live-cook-timer';
import pushNotifications from './push-notifications';

window.cookChart = cookChart;
window.cookDescriptionGallery = cookDescriptionGallery;
window.liveCookTimer = liveCookTimer;
window.pushNotifications = pushNotifications;

if ('serviceWorker' in navigator) {
    pushNotifications.registerServiceWorker().catch(() => {});
}
