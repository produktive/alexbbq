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

// Chart.js layout coordinates use chart.width/chart.height (CSS pixels).
// canvas.width/canvas.height are backing-store pixels — do not mix them.
function cssScale(chart) {
    const canvas = chart.canvas;
    const rect = canvas.getBoundingClientRect();

    return {
        x: rect.width / chart.width,
        y: rect.height / chart.height,
    };
}

function chartPointFromEvent(e, chart) {
    const canvas = chart.canvas;
    const rect = canvas.getBoundingClientRect();
    const { x: scaleX, y: scaleY } = cssScale(chart);

    return {
        x: (e.clientX - rect.left) / scaleX,
        y: (e.clientY - rect.top) / scaleY,
    };
}

function isInChartArea(e, chart) {
    const { x, y } = chartPointFromEvent(e, chart);
    const { left, right, top, bottom } = chart.chartArea;

    return x >= left && x <= right && y >= top && y <= bottom;
}

function clampChartX(x, chart) {
    const { left, right } = chart.chartArea;

    return Math.max(left, Math.min(right, x));
}

function clampDataX(value, chart) {
    const scale = chart.scales.x;

    return Math.max(scale.min, Math.min(scale.max, value));
}

function dataXFromChartX(chartX, chart) {
    return clampDataX(chart.scales.x.getValueForPixel(clampChartX(chartX, chart)), chart);
}

function cssXFromDataValue(chart, value) {
    const { x: scaleX } = cssScale(chart);

    return chart.scales.x.getPixelForValue(value) * scaleX;
}

function cssChartAreaBox(chart) {
    const { x: scaleX, y: scaleY } = cssScale(chart);
    const { left, top, right, bottom } = chart.chartArea;

    return {
        left: left * scaleX,
        top: top * scaleY,
        width: (right - left) * scaleX,
        height: (bottom - top) * scaleY,
    };
}

function pointHasNote(context) {
    return Boolean(context.raw?.note);
}

function notedPointRadius(context) {
    return pointHasNote(context) ? 7 : 4;
}

function notedPointStyle(context) {
    return pointHasNote(context) ? 'rectRot' : 'circle';
}

function notedPointBorderWidth(context) {
    return pointHasNote(context) ? 2 : 1;
}

function notedPointBorderColor(context) {
    return pointHasNote(context) ? '#d97706' : context.dataset.borderColor;
}

const DATASET_POINT_STYLE = {
    pointRadius: notedPointRadius,
    pointStyle: notedPointStyle,
    pointBorderWidth: notedPointBorderWidth,
    pointBorderColor: notedPointBorderColor,
};

export default function cookChart(data, canModify = false) {
    // Chart.js instances are deeply circular. They must never live on Alpine /
    // Livewire reactive state or Livewire 4's toRaw() recurses infinitely
    // when a $wire action is invoked from this component.
    let chart = null;

    // Bound handler references, kept so destroy() removes exactly what init()
    // attached. Also kept off reactive state for the same reason.
    let handlers = {};
    let chartUpdateListener = null;

    return {
        menu: {
            open: false,
            x: 0,
            y: 0,
            pointId: null,
            pointNote: null,
        },

        selection: {
            dragging: false,
            active: false,
            startX: null,
            endX: null,
            ids: [],
            overlayStyle: 'display:none',
        },

        init() {
            if (canModify) {
                chartUpdateListener = this.$wire.on('cook-chart-updated', async () => {
                    const fresh = await this.$wire.call('refreshChartData');
                    this.refreshChart(fresh);
                });
            }

            this.$nextTick(() => {
                chart = new Chart(this.$refs.canvas, {
                    type: 'line',

                    data: {
                        datasets: [
                            {
                                label: 'Food',
                                data: data.food,
                                pointHitRadius: 8,
                                ...DATASET_POINT_STYLE,
                            },
                            {
                                label: 'BBQ',
                                data: data.bbq,
                                pointHitRadius: 8,
                                ...DATASET_POINT_STYLE,
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

                                    afterBody: (items) => {
                                        const note = items[0]?.raw?.note;

                                        if (!note) {
                                            return [];
                                        }

                                        return ['', note];
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

            if (handlers.dragMove) {
                window.removeEventListener('mousemove', handlers.dragMove);
            }

            if (handlers.keydown) {
                window.removeEventListener('keydown', handlers.keydown);
            }

            handlers = {};

            if (chartUpdateListener) {
                chartUpdateListener();
                chartUpdateListener = null;
            }

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
            handlers.dragMove = (e) => this.onMouseMove(e);
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
            const { x } = chartPointFromEvent(e, chart);

            return dataXFromChartX(x, chart);
        },

        updateSelectionOverlay() {
            if (!chart || this.selection.startX === null || this.selection.endX === null) {
                this.selection.overlayStyle = 'display:none';
                return;
            }

            const area = cssChartAreaBox(chart);
            const xScale = chart.scales.x;
            const lo = Math.max(xScale.min, Math.min(this.selection.startX, this.selection.endX));
            const hi = Math.min(xScale.max, Math.max(this.selection.startX, this.selection.endX));

            let left = cssXFromDataValue(chart, lo);
            let right = cssXFromDataValue(chart, hi);

            // Keep the highlight within the plot area horizontally.
            left = Math.max(area.left, Math.min(left, area.left + area.width));
            right = Math.max(area.left, Math.min(right, area.left + area.width));

            this.selection.overlayStyle = `left:${left}px;width:${Math.max(right - left, 1)}px;top:${area.top}px;height:${area.height}px`;
        },

        onMouseDown(e) {
            if (!canModify) return;

            if (e.button !== 0) return; // left button only - right button is handled by contextmenu

            if (!chart?.chartArea || !isInChartArea(e, chart)) return;

            this.menu.open = false;

            this.selection.dragging = true;
            this.selection.active = false;
            this.selection.ids = [];
            this.selection.startX = this.dataXFromEvent(e);
            this.selection.endX = this.selection.startX;
            this.updateSelectionOverlay();

            window.addEventListener('mousemove', handlers.dragMove);
        },

        onMouseMove(e) {
            if (!this.selection.dragging) return;

            this.selection.endX = this.dataXFromEvent(e);
            this.updateSelectionOverlay();
        },

        onMouseUp() {
            if (!this.selection.dragging) return;

            this.selection.dragging = false;
            window.removeEventListener('mousemove', handlers.dragMove);

            const startPx = cssXFromDataValue(chart, this.selection.startX);
            const endPx = cssXFromDataValue(chart, this.selection.endX);

            if (Math.abs(endPx - startPx) < MIN_DRAG_PX) {
                this.clearSelection();
                return;
            }

            const lo = Math.max(
                chart.scales.x.min,
                Math.min(this.selection.startX, this.selection.endX)
            );
            const hi = Math.min(
                chart.scales.x.max,
                Math.max(this.selection.startX, this.selection.endX)
            );

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
                return;
            }

            this.updateSelectionOverlay();
        },

        clearSelection() {
            this.selection.dragging = false;
            this.selection.active = false;
            this.selection.startX = null;
            this.selection.endX = null;
            this.selection.ids = [];
            this.selection.overlayStyle = 'display:none';

            window.removeEventListener('mousemove', handlers.dragMove);
        },

        isWithinSelection(x) {
            const lo = Math.max(
                chart.scales.x.min,
                Math.min(this.selection.startX, this.selection.endX)
            );
            const hi = Math.min(
                chart.scales.x.max,
                Math.max(this.selection.startX, this.selection.endX)
            );

            return x >= lo && x <= hi;
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
            if (!canModify) return;

            e.preventDefault();

            const withinActiveSelection = this.selection.active && this.isWithinSelection(this.dataXFromEvent(e));

            if (withinActiveSelection) {
                this.menu.pointId = null;
                this.menu.pointNote = null;
            } else {
                const point = this.nearestPoint(e);

                if (!point) {
                    this.menu.open = false;
                    return;
                }

                this.clearSelection();
                this.menu.pointId = Number(point.id);
                this.menu.pointNote = point.note || null;
            }

            this.menu.x = 0;
            this.menu.y = 0;
            this.menu.open = true;

            this.$nextTick(() => this.positionContextMenu(e));
        },

        positionContextMenu(e) {
            const root = this.$refs.root;
            const menuEl = this.$refs.menu;

            if (!root || !menuEl) {
                return;
            }

            const rootRect = root.getBoundingClientRect();
            const relX = e.clientX - rootRect.left;
            const relY = e.clientY - rootRect.top;
            const menuW = menuEl.offsetWidth;
            const menuH = menuEl.offsetHeight;
            const pad = 4;

            let left = relX;

            // Keep the menu inside the chart: anchor its right edge at the
            // click when opening to the right would overflow.
            if (left + menuW > rootRect.width - pad) {
                left = relX - menuW;
            }

            left = Math.max(pad, Math.min(left, rootRect.width - menuW - pad));

            let top = relY;

            if (top + menuH > rootRect.height - pad) {
                top = rootRect.height - menuH - pad;
            }

            top = Math.max(pad, top);

            this.menu.x = left;
            this.menu.y = top;
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

            // Force Chart.js to recompute x bounds from the refreshed data so
            // the first remaining point becomes x=0 and axis labels reset.
            delete chart.options.scales.x.min;
            delete chart.options.scales.x.max;

            chart.update();
        },

        addNote() {
            if (!canModify) return;

            const id = this.menu.pointId;
            if (!id) return;

            this.menu.open = false;

            this.$wire.mountAction('addNote', { id });
        },

        removePoint() {
            if (!canModify) return;

            const id = this.menu.pointId;
            if (!id) return;

            this.menu.open = false;

            this.$wire.mountAction('deletePoint', { id });
        },

        removeBefore() {
            if (!canModify) return;

            const id = this.menu.pointId;
            if (!id) return;

            this.menu.open = false;

            this.$wire.mountAction('deleteBefore', { id });
        },

        removeAfter() {
            if (!canModify) return;

            const id = this.menu.pointId;
            if (!id) return;

            this.menu.open = false;

            this.$wire.mountAction('deleteAfter', { id });
        },

        removeSelected() {
            if (!canModify) return;

            if (!this.selection.ids.length) return;

            const ids = this.selection.ids.map(Number);
            this.menu.open = false;
            this.clearSelection();

            this.$wire.mountAction('deleteSelected', { ids });
        },
    };
}
