<?php

use App\Enums\InvestmentSymbolType;
use App\Models\CryptoBalance;
use App\Models\InvestmentProvider;
use App\Models\InvestmentPurchase;
use App\Models\InvestmentSymbol;
use App\Models\MonthlyPortfolioSnapshot;
use App\Models\SavingsAccount;
use App\Services\MonthlyPortfolioSnapshotService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('it aggregates current totals across savings and symbol types', function () {
    $service = app(MonthlyPortfolioSnapshotService::class);
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

test('it splits each month change into contributions and market movement', function () {
    $service = app(MonthlyPortfolioSnapshotService::class);
    $provider = InvestmentProvider::factory()->ibkr()->create();
    $etf = InvestmentSymbol::factory()->create([
        'type' => InvestmentSymbolType::ETF,
        'symbol' => 'VWCE',
    ]);

    MonthlyPortfolioSnapshot::factory()->create([
        'month_date' => '2026-07-01',
        'savings_amount' => '1000.00',
        'bond_amount' => '0.00',
        'etf_amount' => '41085.17',
        'crypto_amount' => '0.00',
        'stock_amount' => '0.00',
        'total_amount' => '42085.17',
    ]);
    MonthlyPortfolioSnapshot::factory()->create([
        'month_date' => '2026-08-01',
        'savings_amount' => '1200.00',
        'bond_amount' => '0.00',
        'etf_amount' => '42608.46',
        'crypto_amount' => '0.00',
        'stock_amount' => '0.00',
        'total_amount' => '43808.46',
    ]);

    InvestmentPurchase::factory()->create([
        'investment_provider_id' => $provider->id,
        'investment_symbol_id' => $etf->id,
        'purchased_at' => '2026-07-14 12:00:00',
        'quantity' => '20.00000000',
        'price_per_unit' => '114.351',
        'fee' => '3.86',
    ]);

    $rows = $service->pageData()['rows'];
    $etfBreakdown = collect($rows[1]['breakdown']['types'])->firstWhere('key', 'etf_amount');

    expect($rows[0]['breakdown']['available'])->toBeFalse()
        ->and($rows[1]['breakdown']['available'])->toBeTrue()
        ->and($etfBreakdown)->toMatchArray([
            'label' => 'ETF',
            'diff_amount' => '1523.29',
            'contribution_amount' => '2287.02',
            'market_amount' => '-763.73',
            'fee_amount' => '3.86',
        ])
        ->and($rows[1]['breakdown']['investments'])->toMatchArray([
            'diff_amount' => '1523.29',
            'contribution_amount' => '2287.02',
            'market_amount' => '-763.73',
        ]);
});

test('the breakdown never counts savings and always reconciles to the bucket diff', function () {
    MonthlyPortfolioSnapshot::factory()->create([
        'month_date' => '2026-07-01',
        'savings_amount' => '1000.00',
        'bond_amount' => '14049.00',
        'etf_amount' => '100.00',
        'crypto_amount' => '50.00',
        'stock_amount' => '10.00',
        'total_amount' => '15209.00',
    ]);
    MonthlyPortfolioSnapshot::factory()->create([
        'month_date' => '2026-08-01',
        'savings_amount' => '9999.00',
        'bond_amount' => '14042.00',
        'etf_amount' => '120.00',
        'crypto_amount' => '40.00',
        'stock_amount' => '10.00',
        'total_amount' => '24211.00',
    ]);

    $breakdown = app(MonthlyPortfolioSnapshotService::class)->pageData()['rows'][1]['breakdown'];

    expect(collect($breakdown['types'])->pluck('key')->all())
        ->toBe(['bond_amount', 'etf_amount', 'crypto_amount', 'stock_amount']);

    foreach ($breakdown['types'] as $type) {
        expect((float) $type['contribution_amount'] + (float) $type['market_amount'])
            ->toBe((float) $type['diff_amount']);
    }

    expect($breakdown['investments']['diff_amount'])->toBe('3.00');
});
