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
            active: false,
            startX: null,
            endX: null,
            ids: [],
        },

        init() {
            this.$nextTick(() => {
                this.chart = new Chart(this.$refs.canvas, {
                    type: 'line',

                    data: {
                        datasets: [
                            {
                                label: 'Food',
                                data: data.food,
                            },
                            {
                                label: 'BBQ',
                                data: data.bbq,
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
            });
        },
    };
}
