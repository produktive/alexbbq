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
        timer: null,

        init() {
            this.sync();
            this.timer = setInterval(() => this.sync(), 1000);
        },

        destroy() {
            if (this.timer) {
                clearInterval(this.timer);
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
