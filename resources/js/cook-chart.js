import Chart from 'chart.js/auto';

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
const MENU_MIN_WIDTH = 224; // matches min-w-56

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

const CHART_COLORS = {
    food: {
        light: { border: 'rgb(45, 212, 191)', area: '45, 212, 191' },
        dark: { border: 'rgb(94, 234, 212)', area: '94, 234, 212' },

        note: 'rgb(45, 212, 191)',
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
        pointBackgroundColor: colors.border,
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
    pointBorderColor: (context) => context.dataset.borderColor,
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

    return {
        loading: shouldLazyLoad,
        loadError: false,

        menu: {
            open: false,
            positioned: false,
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

            if (canModify) {
                this.bindCanvasEvents();
            }
        },

        destroy() {
            const canvas = this.$refs.canvas;

            if (canvas && handlers.mousedown) {
                canvas.removeEventListener('mousedown', handlers.mousedown);
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

            handlers.mousedown = (e) => this.onMouseDown(e);
            handlers.mouseup = (e) => this.onMouseUp(e);
            handlers.dragMove = (e) => this.onMouseMove(e);
            handlers.contextmenu = (e) => this.onContextMenu(e);
            handlers.keydown = (e) => this.onKeyDown(e);

            canvas.addEventListener('mousedown', handlers.mousedown);
            window.addEventListener('mouseup', handlers.mouseup);
            canvas.addEventListener('contextmenu', handlers.contextmenu);
            window.addEventListener('keydown', handlers.keydown);
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

        onMouseDown(e) {
            if (e.button !== 0 || !chart?.chartArea || !isInChartArea(e, chart)) {
                return;
            }

            this.menu.open = false;
            this.menu.positioned = false;

            this.selection.dragging = true;
            this.selection.active = false;
            this.selection.ids = [];
            this.selection.startX = this.dataXFromEvent(e);
            this.selection.endX = this.selection.startX;
            this.updateSelectionOverlay();

            window.addEventListener('mousemove', handlers.dragMove);
        },

        onMouseMove(e) {
            if (!this.selection.dragging) {
                return;
            }

            this.selection.endX = this.dataXFromEvent(e);
            this.updateSelectionOverlay();
        },

        onMouseUp() {
            if (!this.selection.dragging) {
                return;
            }

            this.selection.dragging = false;
            window.removeEventListener('mousemove', handlers.dragMove);

            const startPx = cssXFromDataValue(chart, this.selection.startX);
            const endPx = cssXFromDataValue(chart, this.selection.endX);

            if (Math.abs(endPx - startPx) < MIN_DRAG_PX) {
                this.clearSelection();
                return;
            }

            const { lo, hi } = selectionBounds(chart, this.selection.startX, this.selection.endX);
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
            const { lo, hi } = selectionBounds(chart, this.selection.startX, this.selection.endX);

            return x >= lo && x <= hi;
        },

        nearestPoint(e) {
            const matches = chart.getElementsAtEventForMode(
                e,
                'nearest',
                { intersect: true },
                true
            );

            if (!matches.length) {
                return null;
            }

            const { datasetIndex, index } = matches[0];

            return {
                point: chart.data.datasets[datasetIndex].data[index],
                index,
            };
        },

        onContextMenu(e) {
            e.preventDefault();

            const withinActiveSelection = this.selection.active && this.isWithinSelection(this.dataXFromEvent(e));

            if (withinActiveSelection) {
                this.menu.pointId = null;
                this.menu.pointNote = null;
                this.menu.canDeleteBefore = false;
                this.menu.canDeleteAfter = false;
            } else {
                const hit = this.nearestPoint(e);

                if (!hit) {
                    this.menu.open = false;
                    return;
                }

                const { point, index } = hit;
                const seriesLength = chart.data.datasets[0].data.length;

                this.clearSelection();
                this.menu.pointId = Number(point.id);
                this.menu.pointNote = point.note || null;
                this.menu.canDeleteBefore = index > 0;
                this.menu.canDeleteAfter = index < seriesLength - 1;
            }

            this.menu.positioned = false;
            this.scheduleContextMenuPosition(e);
        },

        scheduleContextMenuPosition(e) {
            this.menu.open = true;

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

            this.menu.open = false;
            this.menu.positioned = false;
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

            chart.data.datasets[0].data = fresh.food;
            chart.data.datasets[1].data = fresh.bbq;

            delete chart.options.scales.x.min;
            delete chart.options.scales.x.max;

            chart.update();
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

            if (!id) {
                return;
            }

            this.menu.open = false;
            this.menu.positioned = false;
            this.$wire.mountAction(name, { id });
        },

        removeSelected() {
            if (!this.selection.ids.length) {
                return;
            }

            const ids = this.selection.ids.map(Number);
            this.menu.open = false;
            this.menu.positioned = false;
            this.clearSelection();

            this.$wire.mountAction('deleteSelected', { ids });
        },
    };
}
