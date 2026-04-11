<?php

use App\Enums\InvestmentSymbolType;
use App\Models\InvestmentPurchase;
use App\Models\InvestmentSymbol;
use App\Models\MonthlyPortfolioSnapshot;
use App\Models\User;

test('statistics page requires authentication', function () {
    $this->get(route('statistics.monthly-summary'))
        ->assertRedirect(route('login'));

    $this->get(route('statistics.yearly-invested'))
        ->assertRedirect(route('login'));
});

test('statistics index redirects to monthly summary', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('statistics.index'))
        ->assertRedirect(route('statistics.monthly-summary'));
});

test('authenticated user can view monthly summary page', function () {
    $user = User::factory()->create();
    $btc = InvestmentSymbol::factory()->crypto('BTC')->create();
    $vwce = InvestmentSymbol::factory()->create([
        'type' => InvestmentSymbolType::ETF,
        'symbol' => 'VWCE',
    ]);

    MonthlyPortfolioSnapshot::factory()->create([
        'month_date' => '2025-01-01',
        'savings_amount' => '1000.00',
        'bond_amount' => '100.00',
        'etf_amount' => '200.00',
        'crypto_amount' => '300.00',
        'stock_amount' => '400.00',
        'total_amount' => '2000.00',
        'source' => MonthlyPortfolioSnapshot::SOURCE_MANUAL,
    ]);
    MonthlyPortfolioSnapshot::factory()->create([
        'month_date' => '2025-02-01',
        'savings_amount' => '1100.00',
        'bond_amount' => '100.00',
        'etf_amount' => '250.00',
        'crypto_amount' => '350.00',
        'stock_amount' => '400.00',
        'total_amount' => '2200.00',
        'source' => MonthlyPortfolioSnapshot::SOURCE_SCHEDULED,
    ]);

    InvestmentPurchase::factory()->create([
        'investment_symbol_id' => $btc->id,
        'purchased_at' => '2024-05-10 09:00:00',
        'quantity' => '0.50000000',
        'price_per_unit' => '1000.00',
        'fee' => '20.00',
    ]);
    InvestmentPurchase::factory()->create([
        'investment_symbol_id' => $vwce->id,
        'purchased_at' => '2025-03-12 09:00:00',
        'quantity' => '10.00000000',
        'price_per_unit' => '500.00',
        'fee' => '25.00',
    ]);

    $this->actingAs($user)
        ->get(route('statistics.monthly-summary'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Statistika/MesecniPovzetek')
            ->has('rows', 2)
            ->where('rows.0.total_amount', '2000.00')
            ->where('rows.1.diff_amount', '200.00')
            ->where('rows.1.diff_percentage', '10.00')
            ->where('latest.month_date', '2025-02-01')
            ->has('chartSeries', 6)
        );
});

test('authenticated user can view yearly invested page', function () {
    $currentYear = now()->year;
    $user = User::factory()->create();
    $btc = InvestmentSymbol::factory()->crypto('BTC')->create();
    $vwce = InvestmentSymbol::factory()->create([
        'type' => InvestmentSymbolType::ETF,
        'symbol' => 'VWCE',
    ]);

    InvestmentPurchase::factory()->create([
        'investment_symbol_id' => $btc->id,
        'purchased_at' => '2024-05-10 09:00:00',
        'quantity' => '0.50000000',
        'price_per_unit' => '1000.00',
        'fee' => '20.00',
    ]);
    InvestmentPurchase::factory()->create([
        'investment_symbol_id' => $vwce->id,
        'purchased_at' => '2025-03-12 09:00:00',
        'quantity' => '10.00000000',
        'price_per_unit' => '500.00',
        'fee' => '25.00',
    ]);

    $this->actingAs($user)
        ->get(route('statistics.yearly-invested'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Statistika/LetniVlozki')
            ->where('years', range(2024, $currentYear))
            ->has('symbols', 2)
            ->where("rows.0.symbols.{$btc->id}.amount", '500.00')
            ->where("rows.1.symbols.{$vwce->id}.amount", '5000.00')
            ->where('totals.grand_total_amount', '5500.00')
        );
});
