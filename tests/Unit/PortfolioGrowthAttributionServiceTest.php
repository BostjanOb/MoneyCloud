<?php

use App\Enums\InvestmentSymbolType;
use App\Enums\InvestmentTransactionType;
use App\Models\InvestmentProvider;
use App\Models\InvestmentPurchase;
use App\Models\InvestmentSymbol;
use App\Services\PortfolioGrowthAttributionService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function attributionEtfSymbol(): InvestmentSymbol
{
    return InvestmentSymbol::factory()->create([
        'type' => InvestmentSymbolType::ETF,
        'symbol' => 'VWCE',
    ]);
}

test('it sums buys per bucket and keeps fees out of contributions', function () {
    $service = new PortfolioGrowthAttributionService;
    $provider = InvestmentProvider::factory()->ibkr()->create();
    $etf = attributionEtfSymbol();
    $stock = InvestmentSymbol::factory()->create([
        'type' => InvestmentSymbolType::STOCK,
        'symbol' => 'AAPL',
    ]);

    InvestmentPurchase::factory()->create([
        'investment_provider_id' => $provider->id,
        'investment_symbol_id' => $etf->id,
        'purchased_at' => '2026-07-10 12:00:00',
        'transaction_type' => InvestmentTransactionType::Buy,
        'quantity' => '10.00000000',
        'price_per_unit' => '100.500',
        'fee' => '1.30',
    ]);
    InvestmentPurchase::factory()->create([
        'investment_provider_id' => $provider->id,
        'investment_symbol_id' => $stock->id,
        'purchased_at' => '2026-07-20 12:00:00',
        'transaction_type' => InvestmentTransactionType::Buy,
        'quantity' => '2.00000000',
        'price_per_unit' => '50.000',
        'fee' => '0.70',
    ]);

    $flows = $service->flowsBetween(
        CarbonImmutable::parse('2026-07-01'),
        CarbonImmutable::parse('2026-08-01'),
    );

    expect($flows['etf_amount'])->toBe(['contribution' => 100500, 'fee' => 130])
        ->and($flows['stock_amount'])->toBe(['contribution' => 10000, 'fee' => 70])
        ->and($flows['bond_amount'])->toBe(['contribution' => 0, 'fee' => 0])
        ->and($flows['crypto_amount'])->toBe(['contribution' => 0, 'fee' => 0]);
});

test('it subtracts sells from contributions', function () {
    $service = new PortfolioGrowthAttributionService;
    $provider = InvestmentProvider::factory()->ibkr()->create();
    $etf = attributionEtfSymbol();

    InvestmentPurchase::factory()->create([
        'investment_provider_id' => $provider->id,
        'investment_symbol_id' => $etf->id,
        'purchased_at' => '2026-07-05 12:00:00',
        'transaction_type' => InvestmentTransactionType::Buy,
        'quantity' => '10.00000000',
        'price_per_unit' => '100.000',
        'fee' => '0.00',
    ]);
    InvestmentPurchase::factory()->create([
        'investment_provider_id' => $provider->id,
        'investment_symbol_id' => $etf->id,
        'purchased_at' => '2026-07-25 12:00:00',
        'transaction_type' => InvestmentTransactionType::Sell,
        'quantity' => '4.00000000',
        'price_per_unit' => '110.000',
        'fee' => '0.00',
    ]);

    $flows = $service->flowsBetween(
        CarbonImmutable::parse('2026-07-01'),
        CarbonImmutable::parse('2026-08-01'),
    );

    expect($flows['etf_amount']['contribution'])->toBe(100000 - 44000);
});

test('it excludes transactions outside the window', function () {
    $service = new PortfolioGrowthAttributionService;
    $provider = InvestmentProvider::factory()->ibkr()->create();
    $etf = attributionEtfSymbol();

    InvestmentPurchase::factory()->create([
        'investment_provider_id' => $provider->id,
        'investment_symbol_id' => $etf->id,
        'purchased_at' => '2026-06-30 23:59:00',
        'quantity' => '1.00000000',
        'price_per_unit' => '100.000',
        'fee' => '0.00',
    ]);
    InvestmentPurchase::factory()->create([
        'investment_provider_id' => $provider->id,
        'investment_symbol_id' => $etf->id,
        'purchased_at' => '2026-08-01 00:00:00',
        'quantity' => '1.00000000',
        'price_per_unit' => '100.000',
        'fee' => '0.00',
    ]);
    InvestmentPurchase::factory()->create([
        'investment_provider_id' => $provider->id,
        'investment_symbol_id' => $etf->id,
        'purchased_at' => '2026-07-01 00:00:00',
        'quantity' => '1.00000000',
        'price_per_unit' => '100.000',
        'fee' => '0.00',
    ]);

    $flows = $service->flowsBetween(
        CarbonImmutable::parse('2026-07-01'),
        CarbonImmutable::parse('2026-08-01'),
    );

    expect($flows['etf_amount']['contribution'])->toBe(10000);
});

test('it groups flows by month and sums a multi month window', function () {
    $service = new PortfolioGrowthAttributionService;
    $provider = InvestmentProvider::factory()->crypto()->create();
    $btc = InvestmentSymbol::factory()->crypto('BTC')->create();

    InvestmentPurchase::factory()->create([
        'investment_provider_id' => $provider->id,
        'investment_symbol_id' => $btc->id,
        'purchased_at' => '2026-06-15 12:00:00',
        'quantity' => '0.00100000',
        'price_per_unit' => '100000.000',
        'fee' => '0.10',
    ]);
    InvestmentPurchase::factory()->create([
        'investment_provider_id' => $provider->id,
        'investment_symbol_id' => $btc->id,
        'purchased_at' => '2026-07-15 12:00:00',
        'quantity' => '0.00200000',
        'price_per_unit' => '100000.000',
        'fee' => '0.20',
    ]);

    $flowsByMonth = $service->flowsGroupedByMonth();

    expect(array_keys($flowsByMonth))->toBe(['2026-06-01', '2026-07-01'])
        ->and($flowsByMonth['2026-06-01']['crypto_amount'])->toBe(['contribution' => 10000, 'fee' => 10]);

    $window = $service->sumMonthlyFlows(
        $flowsByMonth,
        CarbonImmutable::parse('2026-06-01'),
        CarbonImmutable::parse('2026-08-01'),
    );

    expect($window['crypto_amount'])->toBe(['contribution' => 30000, 'fee' => 30]);
});
