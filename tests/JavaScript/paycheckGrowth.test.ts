import assert from 'node:assert/strict';
import test from 'node:test';
import {
    buildPaycheckGrowthSummary,
    displayedPaycheckGrowthSeries,
} from '../../resources/js/lib/paycheckGrowth.ts';

test('displayed paycheck growth series hides bonus variants when bonuses are excluded', () => {
    const series = displayedPaycheckGrowthSeries(
        [
            { key: 'net', label: 'Neto', color: '#1', values: [1, 2] },
            { key: 'gross', label: 'Bruto', color: '#2', values: [3, 4] },
            {
                key: 'net_with_bonuses',
                label: 'Neto z bonusi',
                color: '#3',
                values: [5, 6],
            },
            {
                key: 'gross_with_bonuses',
                label: 'Bruto z bonusi',
                color: '#4',
                values: [7, 8],
            },
        ],
        false,
    );

    assert.deepEqual(
        series.map((item) => item.key),
        ['net', 'gross'],
    );
});

test('paycheck growth summary uses bonus totals when bonuses are included', () => {
    const summary = buildPaycheckGrowthSummary(
        [
            {
                year: 2025,
                is_partial: false,
                recorded_through_month: 12,
                net: '12000.00',
                gross: '18000.00',
                bonuses_gross: '600.00',
                bonuses_net: '500.00',
                gross_with_bonuses: '18600.00',
                net_with_bonuses: '12500.00',
                cumulative_net: [
                    '1000.00',
                    '2000.00',
                    '3000.00',
                    '4000.00',
                    '5000.00',
                    '6000.00',
                    '7000.00',
                    '8000.00',
                    '9000.00',
                    '10000.00',
                    '11000.00',
                    '12000.00',
                ],
                cumulative_gross: [
                    '1500.00',
                    '3000.00',
                    '4500.00',
                    '6000.00',
                    '7500.00',
                    '9000.00',
                    '10500.00',
                    '12000.00',
                    '13500.00',
                    '15000.00',
                    '16500.00',
                    '18000.00',
                ],
                cumulative_bonuses_gross: Array(11).fill('0.00').concat('600.00'),
                cumulative_bonuses_net: Array(11).fill('0.00').concat('500.00'),
            },
            {
                year: 2026,
                is_partial: true,
                recorded_through_month: 2,
                net: '2200.00',
                gross: '3200.00',
                bonuses_gross: '300.00',
                bonuses_net: '200.00',
                gross_with_bonuses: '3500.00',
                net_with_bonuses: '2400.00',
                cumulative_net: [
                    '1100.00',
                    '2200.00',
                    '2200.00',
                    '2200.00',
                    '2200.00',
                    '2200.00',
                    '2200.00',
                    '2200.00',
                    '2200.00',
                    '2200.00',
                    '2200.00',
                    '2200.00',
                ],
                cumulative_gross: [
                    '1600.00',
                    '3200.00',
                    '3200.00',
                    '3200.00',
                    '3200.00',
                    '3200.00',
                    '3200.00',
                    '3200.00',
                    '3200.00',
                    '3200.00',
                    '3200.00',
                    '3200.00',
                ],
                cumulative_bonuses_gross: [
                    '0.00',
                    '300.00',
                    '300.00',
                    '300.00',
                    '300.00',
                    '300.00',
                    '300.00',
                    '300.00',
                    '300.00',
                    '300.00',
                    '300.00',
                    '300.00',
                ],
                cumulative_bonuses_net: [
                    '0.00',
                    '200.00',
                    '200.00',
                    '200.00',
                    '200.00',
                    '200.00',
                    '200.00',
                    '200.00',
                    '200.00',
                    '200.00',
                    '200.00',
                    '200.00',
                ],
            },
        ],
        true,
    );

    assert.deepEqual(summary, {
        latest_year: 2026,
        previous_year: 2025,
        net_change_amount: '400.00',
        net_change_percentage: '20.00',
        gross_change_amount: '500.00',
        gross_change_percentage: '16.67',
    });
});
