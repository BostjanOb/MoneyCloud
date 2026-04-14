import assert from 'node:assert/strict';
import test from 'node:test';
import { buildTrendChartData } from '../../resources/js/lib/dashboard.ts';

test('dashboard trend chart data converts strings into chart-ready values', () => {
    const chartData = buildTrendChartData([
        {
            month_date: '2026-02-01',
            month_label: '1. 2. 2026',
            total_amount: '6800.00',
            diff_amount: null,
        },
        {
            month_date: '2026-03-01',
            month_label: '1. 3. 2026',
            total_amount: '7000.00',
            diff_amount: '200.00',
        },
    ]);

    assert.equal(chartData[0]?.monthLabel, '1. 2. 2026');
    assert.equal(chartData[0]?.totalAmount, 6800);
    assert.equal(chartData[0]?.diffAmount, null);
    assert.equal(chartData[1]?.diffAmount, 200);
    assert.ok(chartData[1]?.monthDate instanceof Date);
});
