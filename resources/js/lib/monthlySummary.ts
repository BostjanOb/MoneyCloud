type MonthlyHistoryRow = {
    month_date: string;
};

export function sortMonthlyHistoryRows<T extends MonthlyHistoryRow>(
    rows: readonly T[],
): T[] {
    return [...rows].sort((left, right) =>
        right.month_date.localeCompare(left.month_date),
    );
}
