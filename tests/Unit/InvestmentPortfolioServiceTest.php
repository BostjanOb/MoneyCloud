<?php

use App\Enums\InvestmentPriceSource;
use App\Enums\InvestmentSymbolType;
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
