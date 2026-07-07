function formatElapsed(seconds) {
    const total = Math.max(0, Math.floor(seconds));
    const hours = Math.floor(total / 3600);
    const minutes = Math.floor((total % 3600) / 60);
    const secs = total % 60;
    const pad = (value) => String(value).padStart(2, '0');

    if (hours > 0) {
        return `${hours}:${pad(minutes)}:${pad(secs)}`;
    }

    return `${minutes}:${pad(secs)}`;
}

function elapsedFromIso(beganAtIso) {
    if (! beganAtIso) {
        return '';
    }

    const beganAt = Date.parse(beganAtIso);

    if (Number.isNaN(beganAt)) {
        return '';
    }

    return formatElapsed((Date.now() - beganAt) / 1000);
}

export default function liveCookTimer(beganAtIso = null) {
    return {
        elapsed: elapsedFromIso(beganAtIso),
        connecting: ! beganAtIso,
        online: navigator.onLine,
        timer: null,
        onlineHandler: null,
        offlineHandler: null,

        init() {
            this.sync();
            this.timer = setInterval(() => this.sync(), 1000);

            this.onlineHandler = () => {
                this.online = true;
            };

            this.offlineHandler = () => {
                this.online = false;
            };

            window.addEventListener('online', this.onlineHandler);
            window.addEventListener('offline', this.offlineHandler);
        },

        destroy() {
            if (this.timer) {
                clearInterval(this.timer);
                this.timer = null;
            }

            if (this.onlineHandler) {
                window.removeEventListener('online', this.onlineHandler);
                this.onlineHandler = null;
            }

            if (this.offlineHandler) {
                window.removeEventListener('offline', this.offlineHandler);
                this.offlineHandler = null;
            }
        },

        sync() {
            const beganAtIso = this.$el.dataset.beganAt;

            this.connecting = ! beganAtIso;
            this.elapsed = elapsedFromIso(beganAtIso);
        },
    };
}

export function registerLiveCookTimer(Alpine) {
    Alpine.data('liveCookTimer', liveCookTimer);
}

export function initLiveCookTimers() {
    if (! window.Alpine) {
        return;
    }

    document.querySelectorAll('[data-live-cook-timer]').forEach((element) => {
        if (element._x_dataStack?.length) {
            return;
        }

        window.Alpine.initTree(element);
    });
}
