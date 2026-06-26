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
        return formatElapsed(0);
    }

    const beganAt = Date.parse(beganAtIso);

    if (Number.isNaN(beganAt)) {
        return formatElapsed(0);
    }

    return formatElapsed((Date.now() - beganAt) / 1000);
}

export default function liveCookTimer(beganAtIso = null) {
    return {
        elapsed: elapsedFromIso(beganAtIso),
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
            this.elapsed = elapsedFromIso(this.$el.dataset.beganAt);
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
