<?php

use App\Enums\InvestmentSymbolType;
use App\Models\CryptoBalance;
use App\Models\InvestmentProvider;
use App\Models\InvestmentPurchase;
use App\Models\InvestmentSymbol;
use App\Models\SavingsAccount;
use App\Services\MonthlyPortfolioSnapshotService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('it aggregates current totals across savings and symbol types', function () {
    $service = new MonthlyPortfolioSnapshotService;
    $investmentProvider = InvestmentProvider::factory()->ibkr()->create();
    $cryptoProvider = InvestmentProvider::factory()->crypto()->create();

    SavingsAccount::factory()->create([
        'amount' => '1000.00',
    ]);
    SavingsAccount::factory()->create([
        'parent_id' => SavingsAccount::factory()->create([
            'amount' => '999.00',
        ])->id,
        'amount' => '400.00',
    ]);

    $bond = InvestmentSymbol::factory()->bond()->create([
        'current_price' => '105.00',
    ]);
    $etf = InvestmentSymbol::factory()->create([
        'type' => InvestmentSymbolType::ETF,
        'symbol' => 'VWCE',
        'current_price' => '90.00',
    ]);
    $stock = InvestmentSymbol::factory()->create([
        'type' => InvestmentSymbolType::STOCK,
        'symbol' => 'AAPL',
        'current_price' => '120.00',
    ]);
    $btc = InvestmentSymbol::factory()->crypto('BTC')->create([
        'current_price' => '50000.00',
    ]);

    InvestmentPurchase::factory()->create([
        'investment_provider_id' => $investmentProvider->id,
        'investment_symbol_id' => $bond->id,
        'quantity' => '2.00000000',
        'price_per_unit' => '100.00',
    ]);
    InvestmentPurchase::factory()->create([
        'investment_provider_id' => $investmentProvider->id,
        'investment_symbol_id' => $etf->id,
        'quantity' => '3.00000000',
        'price_per_unit' => '80.00',
    ]);
    InvestmentPurchase::factory()->create([
        'investment_provider_id' => $investmentProvider->id,
        'investment_symbol_id' => $stock->id,
        'quantity' => '1.50000000',
        'price_per_unit' => '100.00',
    ]);
    InvestmentPurchase::factory()->create([
        'investment_provider_id' => $cryptoProvider->id,
        'investment_symbol_id' => $btc->id,
        'quantity' => '0.10000000',
        'price_per_unit' => '40000.00',
    ]);

    CryptoBalance::factory()->create([
        'investment_provider_id' => $cryptoProvider->id,
        'investment_symbol_id' => $btc->id,
        'manual_quantity' => '0.50000000',
    ]);

    expect($service->currentStateTotals())->toMatchArray([
        'savings_amount' => '1999.00',
        'bond_amount' => '210.00',
        'etf_amount' => '270.00',
        'crypto_amount' => '25000.00',
        'stock_amount' => '180.00',
    ]);
});
