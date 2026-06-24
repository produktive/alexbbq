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

export default function liveCookTimer(beganAtIso) {
    return {
        beganAtIso,
        elapsed: formatElapsed(0),
        timer: null,

        init() {
            this.tick();
            this.timer = setInterval(() => this.tick(), 1000);
        },

        destroy() {
            if (this.timer) {
                clearInterval(this.timer);
            }
        },

        tick() {
            const beganAt = Date.parse(this.beganAtIso);

            if (Number.isNaN(beganAt)) {
                this.elapsed = formatElapsed(0);

                return;
            }

            this.elapsed = formatElapsed((Date.now() - beganAt) / 1000);
        },
    };
}

export function registerLiveCookTimer(Alpine) {
    Alpine.data('liveCookTimer', liveCookTimer);
}

export function initLiveCookTimers() {
    if (!window.Alpine) {
        return;
    }

    document.querySelectorAll('[data-live-cook-timer]').forEach((element) => {
        if (element._x_dataStack?.length) {
            return;
        }

        window.Alpine.initTree(element);
    });
}
