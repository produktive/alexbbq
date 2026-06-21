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
    return {
        chart: null,

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

        // Bound handler references, kept so destroy() removes exactly what
        // init() attached.
        _handlers: {},

        init() {
            this.$nextTick(() => {
                this.chart = new Chart(this.$refs.canvas, {
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

            if (canvas && this._handlers.mousedown) {
                canvas.removeEventListener('mousedown', this._handlers.mousedown);
                canvas.removeEventListener('mousemove', this._handlers.mousemove);
                canvas.removeEventListener('contextmenu', this._handlers.contextmenu);
            }

            if (this._handlers.mouseup) {
                window.removeEventListener('mouseup', this._handlers.mouseup);
            }

            if (this._handlers.keydown) {
                window.removeEventListener('keydown', this._handlers.keydown);
            }
        },

        bindCanvasEvents() {
            const canvas = this.$refs.canvas;

            this._handlers.mousedown = (e) => this.onMouseDown(e);
            this._handlers.mousemove = (e) => this.onMouseMove(e);
            this._handlers.mouseup = (e) => this.onMouseUp(e);
            this._handlers.contextmenu = (e) => this.onContextMenu(e);
            this._handlers.keydown = (e) => this.onKeyDown(e);

            canvas.addEventListener('mousedown', this._handlers.mousedown);
            canvas.addEventListener('mousemove', this._handlers.mousemove);
            // Listen on window (not just the canvas) so a drag that ends
            // outside the canvas bounds still finalizes the selection.
            window.addEventListener('mouseup', this._handlers.mouseup);
            canvas.addEventListener('contextmenu', this._handlers.contextmenu);
            window.addEventListener('keydown', this._handlers.keydown);
        },

        // Converts a mouse event's screen position into a value on the
        // chart's x-axis (elapsed seconds since the first reading).
        dataXFromEvent(e) {
            const rect = this.$refs.canvas.getBoundingClientRect();
            return this.chart.scales.x.getValueForPixel(e.clientX - rect.left);
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

            const startPx = this.chart.scales.x.getPixelForValue(this.selection.startX);
            const endPx = this.chart.scales.x.getPixelForValue(this.selection.endX);

            if (Math.abs(endPx - startPx) < MIN_DRAG_PX) {
                this.clearSelection();
                return;
            }

            const lo = Math.min(this.selection.startX, this.selection.endX);
            const hi = Math.max(this.selection.startX, this.selection.endX);

            // Both datasets share the same x values/ids - one Reading feeds
            // one Food point and one BBQ point - so reading ids off either
            // dataset is enough.
            const points = this.chart.data.datasets[0].data ?? [];

            this.selection.ids = points
                .filter((point) => point.x >= lo && point.x <= hi)
                .map((point) => point.id);

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
            if (!this.chart || this.selection.startX === null || this.selection.endX === null) {
                return 'display:none';
            }

            const lo = Math.min(this.selection.startX, this.selection.endX);
            const hi = Math.max(this.selection.startX, this.selection.endX);

            const left = this.chart.scales.x.getPixelForValue(lo);
            const right = this.chart.scales.x.getPixelForValue(hi);

            return `left:${left}px;width:${Math.max(right - left, 1)}px`;
        },

        // Finds the data point the cursor is actually hovering over (within
        // each point's pointHitRadius), regardless of which dataset it
        // belongs to. Using intersect:true here - rather than "nearest" -
        // means clicking empty space correctly returns nothing, even on a
        // dense chart where points are only a few pixels apart.
        nearestPoint(e) {
            const matches = this.chart.getElementsAtEventForMode(
                e,
                'nearest',
                { intersect: true },
                true
            );

            if (!matches.length) return null;

            const { datasetIndex, index } = matches[0];

            return this.chart.data.datasets[datasetIndex].data[index];
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
                this.menu.pointId = point.id;
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
            if (!fresh || !this.chart) return;

            data.startSecondsOfDay = fresh.startSecondsOfDay;

            this.chart.data.datasets[0].data = fresh.food;
            this.chart.data.datasets[1].data = fresh.bbq;
            this.chart.update();
        },

        async deletePoint() {
            if (!this.menu.pointId) return;

            const fresh = await this.$wire.deletePoint(this.menu.pointId);
            this.menu.open = false;
            this.refreshChart(fresh);
        },

        async deleteBefore() {
            if (!this.menu.pointId) return;

            const fresh = await this.$wire.deleteBefore(this.menu.pointId);
            this.menu.open = false;
            this.refreshChart(fresh);
        },

        async deleteAfter() {
            if (!this.menu.pointId) return;

            const fresh = await this.$wire.deleteAfter(this.menu.pointId);
            this.menu.open = false;
            this.refreshChart(fresh);
        },

        async deleteSelected() {
            if (!this.selection.ids.length) return;

            const ids = [...this.selection.ids];
            this.menu.open = false;
            this.clearSelection();

            const fresh = await this.$wire.deleteSelectedPoints(ids);
            this.refreshChart(fresh);
        },
    };
}
