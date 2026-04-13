import assert from 'node:assert/strict';
import test from 'node:test';
import { sortYearlyInvestmentRows } from '../../resources/js/lib/yearlyInvested.ts';

test('yearly investment rows are sorted from newest to oldest', () => {
    const sortedRows = sortYearlyInvestmentRows([
        { year: 2024, total_amount: '100.00' },
        { year: 2026, total_amount: '300.00' },
        { year: 2025, total_amount: '200.00' },
    ]);

    assert.deepEqual(
        sortedRows.map((row) => row.year),
        [2026, 2025, 2024],
    );
});
