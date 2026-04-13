export type PaycheckGrowthRow = {
    year: number;
    is_partial: boolean;
    recorded_through_month: number | null;
    net: string;
    gross: string;
    bonuses_gross: string;
    bonuses_net: string;
    gross_with_bonuses: string;
    net_with_bonuses: string;
    cumulative_net: string[];
    cumulative_gross: string[];
    cumulative_bonuses_gross: string[];
    cumulative_bonuses_net: string[];
};

export type PaycheckGrowthSeries = {
    key: string;
    label: string;
    color: string;
    values: number[];
};

export type PaycheckGrowthSummary = {
    latest_year: number | null;
    previous_year: number | null;
    net_change_amount: string | null;
    net_change_percentage: string | null;
    gross_change_amount: string | null;
    gross_change_percentage: string | null;
};

export function displayedPaycheckGrowthSeries(
    chartSeries: PaycheckGrowthSeries[],
    includeBonuses: boolean,
): PaycheckGrowthSeries[] {
    const visibleKeys = includeBonuses
        ? ['net_with_bonuses', 'gross_with_bonuses']
        : ['net', 'gross'];

    return chartSeries.filter((series) => visibleKeys.includes(series.key));
}

export function buildPaycheckGrowthSummary(
    rows: PaycheckGrowthRow[],
    includeBonuses: boolean,
): PaycheckGrowthSummary {
    if (rows.length === 0) {
        return emptySummary();
    }

    const latestRow = rows.at(-1);

    if (!latestRow) {
        return emptySummary();
    }

    const previousRow = rows.length > 1 ? rows.at(-2) ?? null : null;
    const comparisonMonth = latestRow.recorded_through_month ?? 12;
    const latestNet = includeBonuses ? latestRow.net_with_bonuses : latestRow.net;
    const latestGross = includeBonuses ? latestRow.gross_with_bonuses : latestRow.gross;
    const previousNet = previousRow
        ? comparableValue(previousRow, comparisonMonth, includeBonuses, 'net')
        : null;
    const previousGross = previousRow
        ? comparableValue(previousRow, comparisonMonth, includeBonuses, 'gross')
        : null;

    return {
        latest_year: latestRow.year,
        previous_year: previousRow?.year ?? null,
        net_change_amount: previousNet !== null
            ? amountDifference(latestNet, previousNet)
            : null,
        net_change_percentage: previousNet !== null
            ? percentageDifference(latestNet, previousNet)
            : null,
        gross_change_amount: previousGross !== null
            ? amountDifference(latestGross, previousGross)
            : null,
        gross_change_percentage: previousGross !== null
            ? percentageDifference(latestGross, previousGross)
            : null,
    };
}

function emptySummary(): PaycheckGrowthSummary {
    return {
        latest_year: null,
        previous_year: null,
        net_change_amount: null,
        net_change_percentage: null,
        gross_change_amount: null,
        gross_change_percentage: null,
    };
}

function amountDifference(current: string, previous: string): string {
    return ((Number(current) - Number(previous)) as number).toFixed(2);
}

function percentageDifference(current: string, previous: string): string | null {
    if (Number(previous) === 0) {
        return null;
    }

    return (((Number(current) - Number(previous)) / Number(previous)) * 100).toFixed(2);
}

function comparableValue(
    row: PaycheckGrowthRow,
    month: number,
    includeBonuses: boolean,
    base: 'net' | 'gross',
): string {
    const index = Math.max(0, Math.min(11, month - 1));
    const baseValues =
        base === 'net' ? row.cumulative_net : row.cumulative_gross;

    if (!includeBonuses) {
        return baseValues[index] ?? '0.00';
    }

    const bonusValues =
        base === 'net'
            ? row.cumulative_bonuses_net
            : row.cumulative_bonuses_gross;

    return (Number(baseValues[index] ?? '0.00') + Number(bonusValues[index] ?? '0.00')).toFixed(2);
}
