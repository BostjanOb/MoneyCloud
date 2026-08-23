export type DashboardTrendPoint = {
    month_date: string;
    month_label: string;
    total_amount: string;
    diff_amount: string | null;
};

export type DashboardTrendChartPoint = {
    monthDate: Date;
    monthLabel: string;
    totalAmount: number;
    diffAmount: number | null;
};

export function buildTrendChartData(
    points: readonly DashboardTrendPoint[],
): DashboardTrendChartPoint[] {
    return points.map((point) => ({
        monthDate: new Date(`${point.month_date}T00:00:00`),
        monthLabel: point.month_label,
        totalAmount: Number(point.total_amount),
        diffAmount:
            point.diff_amount === null ? null : Number(point.diff_amount),
    }));
}

/**
 * Picks a rounded step size that yields roughly 4–8 axis ticks for a span.
 */
function niceStep(span: number): number {
    if (span <= 0) {
        return 1;
    }

    const magnitude = 10 ** Math.floor(Math.log10(span));
    const normalized = span / magnitude;

    if (normalized <= 2) {
        return magnitude / 4;
    }

    if (normalized <= 5) {
        return magnitude / 2;
    }

    return magnitude;
}

/**
 * Builds a Y axis domain zoomed to the data range (instead of starting at 0),
 * padded and rounded to nice values so small changes stay visible.
 *
 * @return {{domain: [number, number], ticks: number[]}}
 */
export function buildTrendYAxis(values: readonly number[]): {
    domain: [number, number];
    ticks: number[];
} {
    if (values.length === 0) {
        return { domain: [0, 1], ticks: [0, 1] };
    }

    const min = Math.min(...values);
    const max = Math.max(...values);
    const span = max - min;
    const padding = span === 0 ? Math.max(Math.abs(max) * 0.1, 1) : span * 0.15;
    const step = niceStep(span + padding * 2);
    const lower = Math.floor((min - padding) / step) * step;
    const upper = Math.ceil((max + padding) / step) * step;
    const ticks: number[] = [];

    for (let value = lower; value <= upper + step / 2; value += step) {
        ticks.push(Number(value.toFixed(6)));
    }

    return { domain: [lower, upper], ticks };
}
