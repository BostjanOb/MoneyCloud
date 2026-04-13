import assert from 'node:assert/strict';
import test from 'node:test';
import { sortMonthlyHistoryRows } from '../../resources/js/lib/monthlySummary.ts';

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
