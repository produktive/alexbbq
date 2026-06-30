import Chart from 'chart.js/auto';
import zoomPlugin from 'chartjs-plugin-zoom';

Chart.register(zoomPlugin);

function formatClock(startSecondsOfDay, elapsedSeconds, { includeSeconds = true } = {}) {
    const wrapped = ((startSecondsOfDay + Math.round(elapsedSeconds)) % 86400 + 86400) % 86400;

    let hours = Math.floor(wrapped / 3600);
    const minutes = Math.floor((wrapped % 3600) / 60);
    const seconds = wrapped % 60;

    const ampm = hours >= 12 ? 'PM' : 'AM';
    hours = hours % 12 || 12;

    const pad = (n) => String(n).padStart(2, '0');

    if (includeSeconds) {
        return `${hours}:${pad(minutes)}:${pad(seconds)} ${ampm}`;
    }

    return `${hours}:${pad(minutes)} ${ampm}`;
}

const MIN_DRAG_PX = 6;
const MIN_ZOOM_RANGE_SECONDS = 300;
const MENU_MIN_WIDTH = 224; // matches min-w-56
const TAP_TOLERANCE_PX = 14; // x-axis only — precise even when points are dense

// Chart.js layout coordinates use chart.width/chart.height (CSS pixels).
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

function dataXFromChartX(chartX, chart) {
    const { left, right } = chart.chartArea;
    const x = Math.max(left, Math.min(right, chartX));
    const scale = chart.scales.x;

    return Math.max(scale.min, Math.min(scale.max, scale.getValueForPixel(x)));
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

function selectionBounds(chart, startX, endX) {
    return {
        lo: Math.max(chart.scales.x.min, Math.min(startX, endX)),
        hi: Math.min(chart.scales.x.max, Math.max(startX, endX)),
    };
}

function pointHasNote(context) {
    return Boolean(context.raw?.note);
}

const NOTE_POINT_COLOR = '#ff1493';

const CHART_COLORS = {
    food: {
        light: { border: 'rgb(45, 212, 191)', area: '45, 212, 191' },
        dark: { border: 'rgb(94, 234, 212)', area: '94, 234, 212' },
    },
    bbq: {
        light: { border: 'rgb(217, 119, 6)', area: '217, 119, 6' },
        dark: { border: 'rgb(245, 158, 11)', area: '245, 158, 11' },
    },
};

function chartPalette() {
    const mode = document.documentElement.classList.contains('dark') ? 'dark' : 'light';

    return {
        food: CHART_COLORS.food[mode],
        bbq: CHART_COLORS.bbq[mode],
    };
}

function seriesAreaFill(context, rgb) {
    const { chart } = context;
    const { ctx, chartArea } = chart;

    if (!chartArea) {
        return `rgba(${rgb}, 0.12)`;
    }

    const dark = document.documentElement.classList.contains('dark');
    const gradient = ctx.createLinearGradient(0, chartArea.top, 0, chartArea.bottom);

    gradient.addColorStop(0, `rgba(${rgb}, ${dark ? 0.22 : 0.16})`);
    gradient.addColorStop(1, `rgba(${rgb}, 0)`);

    return gradient;
}

function lineDataset(label, points, colors) {
    return {
        label,
        data: points,
        borderColor: colors.border,
        backgroundColor: (context) => seriesAreaFill(context, colors.area),
        fill: 'start',
        borderWidth: 2,
        pointHitRadius: 0,
        ...DATASET_POINT_STYLE,
    };
}

const SERIES_SWATCH = {
    legend: {
        labels: {
            generateLabels(chart) {
                return Chart.defaults.plugins.legend.labels.generateLabels(chart).map((label) => ({
                    ...label,
                    fillStyle: chart.data.datasets[label.datasetIndex].borderColor,
                    strokeStyle: chart.data.datasets[label.datasetIndex].borderColor,
                    lineWidth: 0,
                }));
            },
        },
    },
};

const DATASET_POINT_STYLE = {
    pointRadius: (context) => (pointHasNote(context) ? 7 : 4),
    pointStyle: (context) => (pointHasNote(context) ? 'rectRot' : 'circle'),
    pointBorderWidth: (context) => (pointHasNote(context) ? 2 : 1),
    pointBackgroundColor: (context) => (pointHasNote(context) ? NOTE_POINT_COLOR : context.dataset.borderColor),
    pointBorderColor: (context) => (pointHasNote(context) ? NOTE_POINT_COLOR : context.dataset.borderColor),
};

async function fetchChartData(cookId, { editor = false } = {}) {
    const query = editor ? '?editor=1' : '';
    const response = await fetch(`/cooks/${cookId}/chart-data${query}`, {
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
    });

    if (! response.ok) {
        return null;
    }

    return response.json();
}

export default function cookChart(initialData, canModify = false, live = false, cookId = null) {
    let data = initialData;
    let chart = null;
    let handlers = {};
    let chartUpdateListener = null;
    let chartRefreshListener = null;
    const shouldLazyLoad = initialData === null && cookId !== null;
    const touchEditing = window.matchMedia('(pointer: coarse)').matches;

    return {
        canModify,
        touchEditing,
        editMode: false,
        isZoomed: false,
        activePointerId: null,

        loading: shouldLazyLoad,
        loadError: false,

        menu: {
            open: false,
            positioned: false,
            useSheet: false,
            x: 0,
            y: 0,
            pointId: null,
            pointNote: null,
            canDeleteBefore: false,
            canDeleteAfter: false,
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
            if (live) {
                chartRefreshListener = (event) => {
                    this.handleChartRefresh(Number(event.detail?.cookId));
                };

                window.addEventListener('cook-chart-refresh', chartRefreshListener);
            }

            if (canModify) {
                chartUpdateListener = this.$wire.on('cook-chart-updated', async () => {
                    this.refreshChart(await fetchChartData(cookId, { editor: true }));
                });
            }

            if (canModify) {
                this.$watch('editMode', (enabled) => {
                    this.updateInteractionState();

                    if (enabled) {
                        return;
                    }

                    this.closeMenu();
                    this.clearSelection();
                });
            }

            this.$nextTick(() => this.bootstrapChart());
        },

        async bootstrapChart() {
            if (shouldLazyLoad) {
                this.loading = true;
                this.loadError = false;
                data = await fetchChartData(cookId, { editor: canModify });
                this.loading = false;

                if (data === null) {
                    this.loadError = true;

                    return;
                }
            }

            if (! data) {
                return;
            }

            this.$nextTick(() => this.renderChart());
        },

        renderChart() {
            if (chart || ! data) {
                return;
            }

            const colors = chartPalette();

            chart = new Chart(this.$refs.canvas, {
                type: 'line',

                data: {
                    datasets: [
                        lineDataset('Food', data.food, colors.food),
                        lineDataset('BBQ', data.bbq, colors.bbq),
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
                                callback: (value) => formatClock(data.startSecondsOfDay, value, { includeSeconds: false }),
                            },
                        },

                        y: {
                            beginAtZero: false,
                            ticks: {
                                callback: (value) => `${value}°`,
                            },
                        },
                    },

                    plugins: {
                        ...SERIES_SWATCH,
                        zoom: {
                            limits: {
                                x: {
                                    min: 'original',
                                    max: 'original',
                                    minRange: MIN_ZOOM_RANGE_SECONDS,
                                },
                            },
                            pan: {
                                enabled: true,
                                mode: 'x',
                                onPanComplete: () => this.syncZoomState(),
                            },
                            zoom: {
                                mode: 'x',
                                wheel: {
                                    enabled: true,
                                    modifierKey: 'ctrl',
                                },
                                pinch: {
                                    enabled: true,
                                },
                                onZoomComplete: () => this.syncZoomState(),
                            },
                        },
                        tooltip: {
                            callbacks: {
                                title: (items) => formatClock(data.startSecondsOfDay, items[0].parsed.x),

                                label: (context) => {
                                    const value = context.parsed.y;

                                    if (value === null || value === undefined) {
                                        return null;
                                    }

                                    return `${context.dataset.label}: ${value}°`;
                                },

                                labelColor: (context) => ({
                                    borderColor: context.dataset.borderColor,
                                    backgroundColor: context.dataset.borderColor,
                                    borderWidth: 0,
                                }),

                                afterBody: (items) => {
                                    const note = items[0]?.raw?.note;

                                    return note ? ['', note] : [];
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
            this.updateInteractionState();
        },

        exploreActive() {
            return ! canModify || ! this.editMode;
        },

        editingActive() {
            return canModify && this.editMode;
        },

        syncZoomState() {
            this.isZoomed = chart?.isZoomedOrPanned() ?? false;
        },

        syncZoomPanState() {
            if (! chart?.options?.plugins?.zoom) {
                return;
            }

            const explore = this.exploreActive();
            const zoomOptions = chart.options.plugins.zoom;

            zoomOptions.pan.enabled = explore;
            zoomOptions.zoom.wheel.enabled = explore;
            zoomOptions.zoom.pinch.enabled = explore;
            chart.update('none');
            this.syncZoomState();
        },

        resetZoom() {
            if (! chart?.isZoomedOrPanned()) {
                return;
            }

            chart.resetZoom();
            this.syncZoomState();
        },

        closeMenu() {
            this.menu.open = false;
            this.menu.positioned = false;
            this.menu.useSheet = false;
        },

        updateInteractionState() {
            const canvas = this.$refs.canvas;

            if (canvas && this.touchEditing) {
                canvas.classList.add('touch-none');
            }

            if (! chart) {
                return;
            }

            chart.options.plugins.tooltip.enabled = this.exploreActive();
            this.syncZoomPanState();
        },

        destroy() {
            const canvas = this.$refs.canvas;

            if (canvas) {
                if (handlers.pointerdown) {
                    canvas.removeEventListener('pointerdown', handlers.pointerdown);
                    canvas.removeEventListener('pointermove', handlers.pointermove);
                    canvas.removeEventListener('pointerup', handlers.pointerup);
                    canvas.removeEventListener('pointercancel', handlers.pointercancel);
                }

                if (handlers.contextmenu) {
                    canvas.removeEventListener('contextmenu', handlers.contextmenu);
                }

                if (handlers.dblclick) {
                    canvas.removeEventListener('dblclick', handlers.dblclick);
                }
            }

            if (handlers.keydown) {
                window.removeEventListener('keydown', handlers.keydown);
            }

            handlers = {};

            if (chartUpdateListener) {
                chartUpdateListener();
                chartUpdateListener = null;
            }

            if (chartRefreshListener) {
                window.removeEventListener('cook-chart-refresh', chartRefreshListener);
                chartRefreshListener = null;
            }

            if (chart) {
                chart.destroy();
                chart = null;
            }
        },

        bindCanvasEvents() {
            const canvas = this.$refs.canvas;

            handlers.dblclick = (e) => this.onDoubleClick(e);
            canvas.addEventListener('dblclick', handlers.dblclick);

            if (! canModify) {
                return;
            }

            handlers.pointerdown = (e) => this.onPointerDown(e);
            handlers.pointermove = (e) => this.onPointerMove(e);
            handlers.pointerup = (e) => this.onPointerUp(e);
            handlers.pointercancel = (e) => this.onPointerUp(e);
            handlers.contextmenu = (e) => this.onContextMenu(e);
            handlers.keydown = (e) => this.onKeyDown(e);

            canvas.addEventListener('pointerdown', handlers.pointerdown);
            canvas.addEventListener('pointermove', handlers.pointermove);
            canvas.addEventListener('pointerup', handlers.pointerup);
            canvas.addEventListener('pointercancel', handlers.pointercancel);
            canvas.addEventListener('contextmenu', handlers.contextmenu);
            window.addEventListener('keydown', handlers.keydown);
        },

        onDoubleClick(e) {
            if (! this.exploreActive() || ! chart?.isZoomedOrPanned()) {
                return;
            }

            e.preventDefault();
            this.resetZoom();
        },

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
            const { lo, hi } = selectionBounds(chart, this.selection.startX, this.selection.endX);

            let left = cssXFromDataValue(chart, lo);
            let right = cssXFromDataValue(chart, hi);

            left = Math.max(area.left, Math.min(left, area.left + area.width));
            right = Math.max(area.left, Math.min(right, area.left + area.width));

            this.selection.overlayStyle = `left:${left}px;width:${Math.max(right - left, 1)}px;top:${area.top}px;height:${area.height}px`;
        },

        onPointerDown(e) {
            if (! this.editingActive() || e.button !== 0 || ! chart?.chartArea || ! isInChartArea(e, chart)) {
                return;
            }

            this.closeMenu();

            const canvas = this.$refs.canvas;

            canvas.setPointerCapture(e.pointerId);
            this.activePointerId = e.pointerId;

            this.selection.dragging = true;
            this.selection.active = false;
            this.selection.ids = [];
            this.selection.startX = this.dataXFromEvent(e);
            this.selection.endX = this.selection.startX;
            this.updateSelectionOverlay();
        },

        onPointerMove(e) {
            if (! this.selection.dragging || e.pointerId !== this.activePointerId) {
                return;
            }

            this.selection.endX = this.dataXFromEvent(e);
            this.updateSelectionOverlay();
        },

        onPointerUp(e) {
            if (e.pointerId !== this.activePointerId) {
                return;
            }

            const canvas = this.$refs.canvas;

            if (canvas.hasPointerCapture(e.pointerId)) {
                canvas.releasePointerCapture(e.pointerId);
            }

            this.activePointerId = null;

            if (! this.selection.dragging) {
                return;
            }

            this.selection.dragging = false;

            const startPx = cssXFromDataValue(chart, this.selection.startX);
            const endPx = cssXFromDataValue(chart, this.selection.endX);
            const dragPx = Math.abs(endPx - startPx);

            if (dragPx < MIN_DRAG_PX) {
                this.clearSelection();

                if (this.touchEditing) {
                    this.openPointMenu(e);
                }

                return;
            }

            const { lo, hi } = selectionBounds(chart, this.selection.startX, this.selection.endX);
            const points = chart.data.datasets[0].data ?? [];

            this.selection.ids = points
                .filter((point) => point.x >= lo && point.x <= hi)
                .map((point) => Number(point.id));

            this.selection.active = this.selection.ids.length > 0;

            if (! this.selection.active) {
                this.clearSelection();

                return;
            }

            this.updateSelectionOverlay();

            if (this.touchEditing) {
                this.openSelectionMenu(e);
            }
        },

        clearSelection() {
            this.selection.dragging = false;
            this.selection.active = false;
            this.selection.startX = null;
            this.selection.endX = null;
            this.selection.ids = [];
            this.selection.overlayStyle = 'display:none';
        },

        isWithinSelection(x) {
            const { lo, hi } = selectionBounds(chart, this.selection.startX, this.selection.endX);

            return x >= lo && x <= hi;
        },

        nearestPointByX(e) {
            if (! chart?.scales?.x || ! chart.chartArea?.width) {
                return null;
            }

            const dataX = this.dataXFromEvent(e);
            const scale = chart.scales.x;
            const range = scale.max - scale.min;

            if (range <= 0) {
                return null;
            }

            const maxDataDist = TAP_TOLERANCE_PX / (chart.chartArea.width / range);
            const points = chart.data.datasets[0].data ?? [];
            let bestIndex = -1;
            let bestDist = Infinity;

            for (let i = 0; i < points.length; i++) {
                const dist = Math.abs(points[i].x - dataX);

                if (dist < bestDist) {
                    bestDist = dist;
                    bestIndex = i;
                }
            }

            if (bestIndex < 0 || bestDist > maxDataDist) {
                return null;
            }

            return {
                point: points[bestIndex],
                index: bestIndex,
            };
        },

        openPointMenu(e) {
            const hit = this.nearestPointByX(e);

            if (! hit) {
                return;
            }

            const { point, index } = hit;
            const seriesLength = chart.data.datasets[0].data.length;

            this.clearSelection();
            this.menu.pointId = Number(point.id);
            this.menu.pointNote = point.note || null;
            this.menu.canDeleteBefore = index > 0;
            this.menu.canDeleteAfter = index < seriesLength - 1;
            this.menu.useSheet = this.touchEditing;
            this.scheduleMenuOpen(e);
        },

        openSelectionMenu(e) {
            if (! this.selection.active) {
                return;
            }

            this.menu.pointId = null;
            this.menu.pointNote = null;
            this.menu.canDeleteBefore = false;
            this.menu.canDeleteAfter = false;
            this.menu.useSheet = this.touchEditing;
            this.scheduleMenuOpen(e);
        },

        onContextMenu(e) {
            if (this.touchEditing) {
                e.preventDefault();

                return;
            }

            if (! this.editingActive()) {
                return;
            }

            e.preventDefault();

            const withinActiveSelection = this.selection.active && this.isWithinSelection(this.dataXFromEvent(e));

            if (withinActiveSelection) {
                this.openSelectionMenu(e);

                return;
            }

            this.openPointMenu(e);
        },

        scheduleMenuOpen(e) {
            this.menu.open = true;

            if (this.menu.useSheet) {
                this.menu.positioned = true;

                return;
            }

            this.menu.positioned = false;

            this.$nextTick(() => {
                this.positionContextMenu(e);

                if (this.$refs.menu?.offsetWidth > 0) {
                    this.menu.positioned = true;

                    return;
                }

                requestAnimationFrame(() => {
                    this.positionContextMenu(e);
                    this.menu.positioned = true;
                });
            });
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
            const menuW = menuEl.offsetWidth || menuEl.scrollWidth || MENU_MIN_WIDTH;
            const menuH = menuEl.offsetHeight || menuEl.scrollHeight || 0;
            const pad = 4;

            let left = relX;

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
            if (e.key !== 'Escape') {
                return;
            }

            this.closeMenu();
            this.clearSelection();
        },

        refreshChart(fresh) {
            if (! fresh) {
                return;
            }

            data = fresh;

            if (! chart) {
                this.loading = false;
                this.loadError = false;
                this.$nextTick(() => this.renderChart());

                return;
            }

            const preserveZoom = chart.isZoomedOrPanned();
            const xBounds = preserveZoom
                ? { min: chart.scales.x.min, max: chart.scales.x.max }
                : null;

            chart.data.datasets[0].data = fresh.food;
            chart.data.datasets[1].data = fresh.bbq;

            if (xBounds) {
                chart.options.scales.x.min = xBounds.min;
                chart.options.scales.x.max = xBounds.max;
            } else {
                delete chart.options.scales.x.min;
                delete chart.options.scales.x.max;
            }

            chart.update();
            this.syncZoomState();
        },

        async handleChartRefresh(eventCookId) {
            const id = eventCookId != null ? Number(eventCookId) : null;
            const chartCookId = cookId != null ? Number(cookId) : null;

            if (id === null || chartCookId === null || id !== chartCookId) {
                return;
            }

            this.refreshChart(await fetchChartData(cookId, { editor: canModify }));
        },

        mountPointAction(name) {
            const id = this.menu.pointId;

            if (! id) {
                return;
            }

            this.closeMenu();
            this.$wire.mountAction(name, { id });
        },

        removeSelected() {
            if (! this.selection.ids.length) {
                return;
            }

            const ids = this.selection.ids.map(Number);

            this.closeMenu();
            this.clearSelection();

            this.$wire.mountAction('deleteSelected', { ids });
        },
    };
}
