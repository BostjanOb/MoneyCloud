import assert from 'node:assert/strict';
import test from 'node:test';
import {
    buildMonthlyChartData,
    sortMonthlyHistoryRows,
} from '../../resources/js/lib/monthlySummary.ts';

test('monthly history rows are sorted from newest to oldest', () => {
    const sortedRows = sortMonthlyHistoryRows([
        { id: 1, month_date: '2025-01-01', month_label: '1. 1. 2025' },
        { id: 2, month_date: '2025-03-01', month_label: '1. 3. 2025' },
        { id: 3, month_date: '2025-02-01', month_label: '1. 2. 2025' },
    ]);

    assert.deepEqual(
        sortedRows.map((row) => row.id),
        [2, 3, 1],
    );
});

test('monthly chart data aligns row labels with series values', () => {
    const chartData = buildMonthlyChartData(
        [
            {
                id: 1,
                month_date: '2025-01-01',
                month_label: 'Jan 2025',
            },
            {
                id: 2,
                month_date: '2025-02-01',
                month_label: 'Feb 2025',
            },
        ],
        [
            { key: 'savings_amount', values: [1000, 1100] },
            { key: 'total_amount', values: [2000, 2200] },
        ],
    );

    assert.equal(chartData[0]?.monthLabel, 'Jan 2025');
    assert.equal(chartData[0]?.savings_amount, 1000);
    assert.equal(chartData[1]?.total_amount, 2200);
});
