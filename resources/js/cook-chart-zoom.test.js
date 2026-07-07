import { describe, expect, it } from 'vitest';
import { xAxisIsZoomed } from './cook-chart-zoom.js';

describe('xAxisIsZoomed', () => {
    it('returns false when chart or bounds are missing', () => {
        expect(xAxisIsZoomed(null, { min: 0, max: 100 })).toBe(false);
        expect(xAxisIsZoomed({ scales: { x: { min: 0, max: 100 } } }, null)).toBe(false);
    });

    it('returns false when scale matches original bounds', () => {
        const chart = { scales: { x: { min: 0, max: 600 } } };
        const bounds = { min: 0, max: 600 };

        expect(xAxisIsZoomed(chart, bounds)).toBe(false);
    });

    it('returns true when min or max differs from original bounds', () => {
        const bounds = { min: 0, max: 600 };

        expect(xAxisIsZoomed({ scales: { x: { min: 100, max: 600 } } }, bounds)).toBe(true);
        expect(xAxisIsZoomed({ scales: { x: { min: 0, max: 300 } } }, bounds)).toBe(true);
    });
});
