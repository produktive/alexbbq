import Chart from 'chart.js/auto';

// Reconstructs a 12-hour clock string from a start-of-day offset plus an
// elapsed-seconds duration, using plain arithmetic only. We deliberately
// never touch a JS Date object for this: Date's local-time getters/formatters
// always defer to the browser's timezone, which is exactly what caused the
// original time display bug.
function formatClock(startSecondsOfDay, elapsedSeconds) {
    const wrapped = ((startSecondsOfDay + Math.round(elapsedSeconds)) % 86400 + 86400) % 86400;

    let hours = Math.floor(wrapped / 3600);
    const minutes = Math.floor((wrapped % 3600) / 60);
    const seconds = wrapped % 60;

    const ampm = hours >= 12 ? 'PM' : 'AM';
    hours = hours % 12 || 12;

    const pad = (n) => String(n).padStart(2, '0');

    return `${hours}:${pad(minutes)}:${pad(seconds)} ${ampm}`;
}

// Minimum drag distance (in pixels) before a mousedown+mousemove counts as a
// range selection instead of an accidental click.
const MIN_DRAG_PX = 6;

export default function cookChart(data) {
    // Chart.js instances are deeply circular. They must never live on Alpine /
    // Livewire reactive state or Livewire 4's toRaw() recurses infinitely
    // when a $wire action is invoked from this component.
    let chart = null;

    // Bound handler references, kept so destroy() removes exactly what init()
    // attached. Also kept off reactive state for the same reason.
    let handlers = {};

    return {
        menu: {
            open: false,
            x: 0,
            y: 0,
            pointId: null,
        },

        selection: {
            dragging: false,
            active: false,
            startX: null,
            endX: null,
            ids: [],
        },

        init() {
            this.$nextTick(() => {
                chart = new Chart(this.$refs.canvas, {
                    type: 'line',

                    data: {
                        datasets: [
                            {
                                label: 'Food',
                                data: data.food,
                                pointHitRadius: 8,
                            },
                            {
                                label: 'BBQ',
                                data: data.bbq,
                                pointHitRadius: 8,
                            },
                        ],
                    },

                    options: {
                        responsive: true,
                        maintainAspectRatio: false,

                        scales: {
                            x: {
                                type: 'linear',
                                bounds: 'data',
                                ticks: {
                                    callback: (value) => formatClock(data.startSecondsOfDay, value),
                                },
                            },

                            y: {
                                beginAtZero: false,
                            },
                        },

                        plugins: {
                            tooltip: {
                                callbacks: {
                                    title: (items) => formatClock(data.startSecondsOfDay, items[0].parsed.x),

                                    label: (context) => {
                                        return `${context.dataset.label}: ${context.parsed.y}°`;
                                    },
                                },
                            },
                        },

                        interaction: {
                            mode: 'nearest',
                            intersect: true,
                        },
                    },
                });

                this.bindCanvasEvents();
            });
        },

        destroy() {
            const canvas = this.$refs.canvas;

            if (canvas && handlers.mousedown) {
                canvas.removeEventListener('mousedown', handlers.mousedown);
                canvas.removeEventListener('mousemove', handlers.mousemove);
                canvas.removeEventListener('contextmenu', handlers.contextmenu);
            }

            if (handlers.mouseup) {
                window.removeEventListener('mouseup', handlers.mouseup);
            }

            if (handlers.keydown) {
                window.removeEventListener('keydown', handlers.keydown);
            }

            handlers = {};

            if (chart) {
                chart.destroy();
                chart = null;
            }
        },

        bindCanvasEvents() {
            const canvas = this.$refs.canvas;

            handlers.mousedown = (e) => this.onMouseDown(e);
            handlers.mousemove = (e) => this.onMouseMove(e);
            handlers.mouseup = (e) => this.onMouseUp(e);
            handlers.contextmenu = (e) => this.onContextMenu(e);
            handlers.keydown = (e) => this.onKeyDown(e);

            canvas.addEventListener('mousedown', handlers.mousedown);
            canvas.addEventListener('mousemove', handlers.mousemove);
            // Listen on window (not just the canvas) so a drag that ends
            // outside the canvas bounds still finalizes the selection.
            window.addEventListener('mouseup', handlers.mouseup);
            canvas.addEventListener('contextmenu', handlers.contextmenu);
            window.addEventListener('keydown', handlers.keydown);
        },

        // Converts a mouse event's screen position into a value on the
        // chart's x-axis (elapsed seconds since the first reading).
        dataXFromEvent(e) {
            const rect = this.$refs.canvas.getBoundingClientRect();
            return chart.scales.x.getValueForPixel(e.clientX - rect.left);
        },

        onMouseDown(e) {
            if (e.button !== 0) return; // left button only - right button is handled by contextmenu

            this.menu.open = false;

            this.selection.dragging = true;
            this.selection.active = false;
            this.selection.ids = [];
            this.selection.startX = this.dataXFromEvent(e);
            this.selection.endX = this.selection.startX;
        },

        onMouseMove(e) {
            if (!this.selection.dragging) return;

            this.selection.endX = this.dataXFromEvent(e);
        },

        onMouseUp() {
            if (!this.selection.dragging) return;

            this.selection.dragging = false;

            const startPx = chart.scales.x.getPixelForValue(this.selection.startX);
            const endPx = chart.scales.x.getPixelForValue(this.selection.endX);

            if (Math.abs(endPx - startPx) < MIN_DRAG_PX) {
                this.clearSelection();
                return;
            }

            const lo = Math.min(this.selection.startX, this.selection.endX);
            const hi = Math.max(this.selection.startX, this.selection.endX);

            // Both datasets share the same x values/ids - one Reading feeds
            // one Food point and one BBQ point - so reading ids off either
            // dataset is enough.
            const points = chart.data.datasets[0].data ?? [];

            this.selection.ids = points
                .filter((point) => point.x >= lo && point.x <= hi)
                .map((point) => Number(point.id));

            this.selection.active = this.selection.ids.length > 0;

            if (!this.selection.active) {
                this.clearSelection();
            }
        },

        clearSelection() {
            this.selection.dragging = false;
            this.selection.active = false;
            this.selection.startX = null;
            this.selection.endX = null;
            this.selection.ids = [];
        },

        isWithinSelection(x) {
            const lo = Math.min(this.selection.startX, this.selection.endX);
            const hi = Math.max(this.selection.startX, this.selection.endX);
            return x >= lo && x <= hi;
        },

        // Style binding for the highlighted drag-selection overlay div.
        selectionOverlayStyle() {
            if (!chart || this.selection.startX === null || this.selection.endX === null) {
                return 'display:none';
            }

            const lo = Math.min(this.selection.startX, this.selection.endX);
            const hi = Math.max(this.selection.startX, this.selection.endX);

            const left = chart.scales.x.getPixelForValue(lo);
            const right = chart.scales.x.getPixelForValue(hi);

            return `left:${left}px;width:${Math.max(right - left, 1)}px`;
        },

        // Finds the data point the cursor is actually hovering over (within
        // each point's pointHitRadius), regardless of which dataset it
        // belongs to. Using intersect:true here - rather than "nearest" -
        // means clicking empty space correctly returns nothing, even on a
        // dense chart where points are only a few pixels apart.
        nearestPoint(e) {
            const matches = chart.getElementsAtEventForMode(
                e,
                'nearest',
                { intersect: true },
                true
            );

            if (!matches.length) return null;

            const { datasetIndex, index } = matches[0];

            return chart.data.datasets[datasetIndex].data[index];
        },

        onContextMenu(e) {
            e.preventDefault();

            const withinActiveSelection = this.selection.active && this.isWithinSelection(this.dataXFromEvent(e));

            if (withinActiveSelection) {
                this.menu.pointId = null;
            } else {
                const point = this.nearestPoint(e);

                if (!point) {
                    this.menu.open = false;
                    return;
                }

                this.clearSelection();
                this.menu.pointId = Number(point.id);
            }

            this.menu.x = e.clientX;
            this.menu.y = e.clientY;
            this.menu.open = true;
        },

        onKeyDown(e) {
            if (e.key !== 'Escape') return;

            this.menu.open = false;
            this.clearSelection();
        },

        // Applies fresh chart data - the resolved return value of a $wire
        // delete call - without re-creating the chart. We mutate `data`
        // itself rather than reassigning a local variable, so the
        // tick/tooltip callbacks above - which closed over `data` - pick up
        // the new startSecondsOfDay automatically (this matters because
        // deleting the first reading shifts every remaining point's
        // elapsed-time offset).
        refreshChart(fresh) {
            if (!fresh || !chart) return;

            data.startSecondsOfDay = fresh.startSecondsOfDay;

            chart.data.datasets[0].data = fresh.food;
            chart.data.datasets[1].data = fresh.bbq;
            chart.update();
        },

        async removePoint() {
            const id = this.menu.pointId;
            if (!id) return;

            this.menu.open = false;

            const fresh = await this.$wire.call('deletePoint', id);
            this.refreshChart(fresh);
        },

        async removeBefore() {
            const id = this.menu.pointId;
            if (!id) return;

            this.menu.open = false;

            const fresh = await this.$wire.call('deleteBefore', id);
            this.refreshChart(fresh);
        },

        async removeAfter() {
            const id = this.menu.pointId;
            if (!id) return;

            this.menu.open = false;

            const fresh = await this.$wire.call('deleteAfter', id);
            this.refreshChart(fresh);
        },

        async removeSelected() {
            if (!this.selection.ids.length) return;

            const ids = this.selection.ids.map(Number);
            this.menu.open = false;
            this.clearSelection();

            const fresh = await this.$wire.call('deleteSelectedPoints', ids);
            this.refreshChart(fresh);
        },
    };
}
