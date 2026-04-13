type YearlyInvestmentRow = {
    year: number;
};

export function sortYearlyInvestmentRows<T extends YearlyInvestmentRow>(
    rows: readonly T[],
): T[] {
    return [...rows].sort((left, right) => right.year - left.year);
}
