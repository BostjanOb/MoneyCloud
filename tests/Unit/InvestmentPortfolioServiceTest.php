<?php

use App\Enums\InvestmentPriceSource;
use App\Enums\InvestmentSymbolType;
use App\Models\InvestmentProvider;
use App\Models\InvestmentPurchase;
use App\Models\InvestmentSymbol;
use App\Services\InvestmentPortfolioService;
use Carbon\CarbonImmutable;
use Tests\TestCase;

uses(TestCase::class);

test('it calculates purchase metrics including after tax profit', function () {
    $service = new InvestmentPortfolioService;
    $symbol = new InvestmentSymbol([
        'type' => InvestmentSymbolType::ETF,
        'symbol' => 'VWCE',
        'taxable' => true,
        'price_source' => InvestmentPriceSource::MANUAL->value,
        'current_price' => '120.00',
    ]);
    $purchase = new InvestmentPurchase([
        'quantity' => '2.00000000',
        'price_per_unit' => '100.00',
        'fee' => '10.00',
        'purchased_at' => CarbonImmutable::parse('2023-04-09 10:00:00'),
    ]);
    $purchase->setRelation('symbol', $symbol);

    $metrics = $service->calculateMetrics(
        $purchase,
        CarbonImmutable::parse('2026-04-09 10:00:00'),
    );

    expect($metrics)
        ->toMatchArray([
            'price' => '200.00',
            'current_value' => '240.00',
            'unit_diff_percentage' => '20.00',
            'profit_loss' => '30.00',
            'profit_loss_after_tax' => '21.10',
            'tax_liability' => '8.90',
        ]);
});

test('it skips tax for non taxable symbols and losses', function () {
    $service = new InvestmentPortfolioService;
    $symbol = new InvestmentSymbol([
        'type' => InvestmentSymbolType::CRYPTO,
        'symbol' => 'BTC',
        'taxable' => false,
        'price_source' => InvestmentPriceSource::MANUAL->value,
        'current_price' => '80.00',
    ]);
    $purchase = new InvestmentPurchase([
        'quantity' => '1.50000000',
        'price_per_unit' => '100.00',
        'fee' => '5.00',
        'purchased_at' => CarbonImmutable::parse('2025-04-09 10:00:00'),
    ]);
    $purchase->setRelation('symbol', $symbol);

    $metrics = $service->calculateMetrics(
        $purchase,
        CarbonImmutable::parse('2026-04-09 10:00:00'),
    );

    expect($metrics['profit_loss'])->toBe('-35.00')
        ->and($metrics['profit_loss_after_tax'])->toBe('-35.00')
        ->and($metrics['tax_liability'])->toBe('0.00');
});

test('it returns signed metrics for sell transactions', function () {
    $service = new InvestmentPortfolioService;
    $symbol = new InvestmentSymbol([
        'type' => InvestmentSymbolType::CRYPTO,
        'symbol' => 'ETH',
        'taxable' => false,
        'price_source' => InvestmentPriceSource::MANUAL->value,
        'current_price' => '2500.00',
    ]);
    $purchase = new InvestmentPurchase([
        'transaction_type' => 'sell',
        'quantity' => '0.50000000',
        'price_per_unit' => '3000.00',
        'fee' => '6.00',
        'purchased_at' => CarbonImmutable::parse('2026-04-01 10:00:00'),
    ]);
    $purchase->setRelation('symbol', $symbol);

    $metrics = $service->calculateMetrics($purchase);

    expect($metrics)
        ->toMatchArray([
            'price' => '-1500.00',
            'current_value' => '-1250.00',
            'unit_diff_percentage' => '-16.67',
            'profit_loss' => '244.00',
            'profit_loss_after_tax' => '244.00',
            'tax_liability' => '0.00',
        ]);
});

test('it summarizes a symbol with buy price statistics', function () {
    $service = new InvestmentPortfolioService;
    $symbol = new InvestmentSymbol([
        'type' => InvestmentSymbolType::STOCK,
        'symbol' => 'AAPL',
        'taxable' => false,
        'price_source' => InvestmentPriceSource::MANUAL->value,
        'current_price' => '120.00',
    ]);
    $symbol->id = 7;

    $purchases = collect([
        new InvestmentPurchase([
            'investment_symbol_id' => 7,
            'transaction_type' => 'buy',
            'quantity' => '2.00000000',
            'price_per_unit' => '100.000',
            'fee' => '10.00',
            'purchased_at' => CarbonImmutable::parse('2024-03-12 10:00:00'),
        ]),
        new InvestmentPurchase([
            'investment_symbol_id' => 7,
            'transaction_type' => 'buy',
            'quantity' => '1.00000000',
            'price_per_unit' => '80.000',
            'fee' => '5.00',
            'purchased_at' => CarbonImmutable::parse('2025-07-02 10:00:00'),
        ]),
        new InvestmentPurchase([
            'investment_symbol_id' => 7,
            'transaction_type' => 'sell',
            'quantity' => '0.50000000',
            'price_per_unit' => '130.000',
            'fee' => '2.00',
            'purchased_at' => CarbonImmutable::parse('2026-01-15 10:00:00'),
        ]),
    ])->each(fn (InvestmentPurchase $purchase) => $purchase->setRelation('symbol', $symbol));

    $provider = new InvestmentProvider(['slug' => 'ibkr', 'name' => 'IBKR']);
    $provider->setRelation('purchases', $purchases);

    $rows = $service->summarizeProviderBySymbol($provider);

    expect($rows)->toHaveCount(1);

    expect($rows[0])->toMatchArray([
        'symbol_id' => 7,
        'symbol' => 'AAPL',
        'current_price' => '120.000',
        'current_value' => '300.00',
        'return_percentage' => '31.63',
        'quantity' => '2.50000000',
        'total_invested' => '215.00',
        'profit_loss' => '68.00',
        'profit_loss_after_tax' => '68.00',
    ]);

    expect($rows[0]['stats'])->toMatchArray([
        'buy_count' => 2,
        'sell_count' => 1,
        'lowest_buy_price' => '80.000',
        'highest_buy_price' => '100.000',
        'average_buy_price' => '93.333',
        'break_even_price' => '98.333',
        'quantity_bought' => '3.00000000',
        'quantity_sold' => '0.50000000',
        'total_fees' => '17.00',
    ]);

    expect($rows[0]['stats']['first_purchase_at'])->toStartWith('2024-03-12')
        ->and($rows[0]['stats']['last_purchase_at'])->toStartWith('2026-01-15');
});

test('it uses normalized ljse bond prices as eur unit prices', function () {
    $service = new InvestmentPortfolioService;
    $symbol = new InvestmentSymbol([
        'type' => InvestmentSymbolType::BOND,
        'symbol' => 'RS94',
        'taxable' => true,
        'price_source' => InvestmentPriceSource::LJSE->value,
        'current_price' => '1005.00',
    ]);
    $purchase = new InvestmentPurchase([
        'quantity' => '2.00000000',
        'price_per_unit' => '995.00',
        'fee' => '4.00',
        'purchased_at' => CarbonImmutable::parse('2026-04-01 10:00:00'),
    ]);
    $purchase->setRelation('symbol', $symbol);

    $metrics = $service->calculateMetrics(
        $purchase,
        CarbonImmutable::parse('2026-04-09 10:00:00'),
    );

    expect($metrics)
        ->toMatchArray([
            'price' => '1990.00',
            'current_value' => '2010.00',
            'unit_diff_percentage' => '1.01',
            'profit_loss' => '16.00',
        ]);
});
