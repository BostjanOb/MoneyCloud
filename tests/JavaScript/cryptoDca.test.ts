import assert from 'node:assert/strict';
import test from 'node:test';
import {
    buildCryptoDcaChartData,
    countBuysBelowCurrentPrice,
} from '../../resources/js/lib/cryptoDca.ts';

const purchases = [
    {
        purchased_at: '2026-03-10T10:00:00.000Z',
        price_per_unit: '55000.000',
        quantity: '0.05000000',
        transaction_type: 'sell' as const,
        transaction_type_label: 'Prodaja',
        provider: { name: 'Binance' },
    },
    {
        purchased_at: '2026-01-10T10:00:00.000Z',
        price_per_unit: '40000.000',
        quantity: '0.10000000',
        transaction_type: 'buy' as const,
        transaction_type_label: 'Nakup',
        provider: { name: 'Binance' },
    },
    {
        purchased_at: '2026-02-10T10:00:00.000Z',
        price_per_unit: '60000.000',
        quantity: '0.10000000',
        transaction_type: 'buy' as const,
        transaction_type_label: 'Nakup',
        provider: { name: 'Revolut' },
    },
];

test('crypto dca chart data is sorted oldest first and carries the current price', () => {
    const chartData = buildCryptoDcaChartData(purchases, '50000.00');

    assert.equal(chartData.length, 3);
    assert.equal(chartData[0]?.price, 40000);
    assert.equal(chartData[1]?.price, 60000);
    assert.equal(chartData[2]?.transactionType, 'sell');
    assert.equal(chartData[0]?.providerName, 'Binance');
    assert.equal(chartData[1]?.providerName, 'Revolut');
    assert.ok(
        chartData.every((point) => point.currentPrice === 50000),
        'every point carries the current price',
    );
    assert.ok(chartData[0]!.timestamp < chartData[1]!.timestamp);
});

test('only buys below the current price are counted, sells are ignored', () => {
    const split = countBuysBelowCurrentPrice(
        buildCryptoDcaChartData(purchases, '50000.00'),
    );

    assert.deepEqual(split, { below: 1, total: 2 });
});

test('buys at exactly the current price do not count as below', () => {
    const split = countBuysBelowCurrentPrice(
        buildCryptoDcaChartData(purchases, '40000.00'),
    );

    assert.deepEqual(split, { below: 0, total: 2 });
});

test('empty transaction lists produce empty chart data and zero counts', () => {
    const chartData = buildCryptoDcaChartData([], '50000.00');

    assert.deepEqual(chartData, []);
    assert.deepEqual(countBuysBelowCurrentPrice(chartData), {
        below: 0,
        total: 0,
    });
});
