type MonthlyHistoryRow = {
    month_date: string;
    month_label: string;
};

type MonthlyChartSeries = {
    key: string;
    values: number[];
};

export type MonthlySummaryChartPoint = {
    monthDate: Date;
    monthLabel: string;
    [key: string]: Date | string | number;
};

export function sortMonthlyHistoryRows<T extends MonthlyHistoryRow>(
    rows: readonly T[],
): T[] {
    return [...rows].sort((left, right) =>
        right.month_date.localeCompare(left.month_date),
    );
}

export function buildMonthlyChartData<
    TRow extends MonthlyHistoryRow,
    TSeries extends MonthlyChartSeries,
>(
    rows: readonly TRow[],
    chartSeries: readonly TSeries[],
): MonthlySummaryChartPoint[] {
    return rows.map((row, index) => ({
        monthDate: new Date(`${row.month_date}T00:00:00`),
        monthLabel: row.month_label,
        ...Object.fromEntries(
            chartSeries.map((series) => [
                series.key,
                series.values[index] ?? 0,
            ]),
        ),
    }));
}
