export function xAxisIsZoomed(chart, originalXBounds) {
    if (! chart?.scales?.x || ! originalXBounds) {
        return false;
    }

    const xScale = chart.scales.x;

    return xScale.min !== originalXBounds.min || xScale.max !== originalXBounds.max;
}
