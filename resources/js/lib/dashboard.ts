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
