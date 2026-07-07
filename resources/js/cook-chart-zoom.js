export const ZOOM_BOUNDS_EPSILON_SECONDS = 0.5;

function boundsMatch(a, b) {
    return Math.abs(a - b) <= ZOOM_BOUNDS_EPSILON_SECONDS;
}

export function xAxisIsZoomed(chart, originalXBounds) {
    if (! chart?.scales?.x || ! originalXBounds) {
        return false;
    }

    const xScale = chart.scales.x;

    return ! boundsMatch(xScale.min, originalXBounds.min)
        || ! boundsMatch(xScale.max, originalXBounds.max);
}
