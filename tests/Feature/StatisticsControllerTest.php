<?php

use App\Enums\InvestmentSymbolType;
use App\Models\Bonus;
use App\Models\CryptoBalance;
use App\Models\InvestmentProvider;
use App\Models\InvestmentPurchase;
use App\Models\InvestmentSymbol;
use App\Models\MonthlyPortfolioSnapshot;
use App\Models\Paycheck;
use App\Models\PaycheckYear;
use App\Models\Person;
use App\Models\SavingsAccount;
use App\Models\User;
use Carbon\CarbonImmutable;

test('statistics page requires authentication', function () {
    $this->get(route('statistics.monthly-summary'))
        ->assertRedirect(route('login'));

    $this->get(route('statistics.yearly-invested'))
        ->assertRedirect(route('login'));

    $this->get(route('statistics.paycheck-growth'))
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
    $bond = InvestmentSymbol::factory()->bond()->create([
        'symbol' => 'BOND',
        'current_price' => '100.00',
    ]);
    $btc = InvestmentSymbol::factory()->crypto('BTC')->create([
        'current_price' => '200.00',
    ]);
    $vwce = InvestmentSymbol::factory()->create([
        'type' => InvestmentSymbolType::ETF,
        'symbol' => 'VWCE',
        'current_price' => '100.00',
    ]);
    $krka = InvestmentSymbol::factory()->create([
        'type' => InvestmentSymbolType::STOCK,
        'symbol' => 'KRKG',
        'current_price' => '100.00',
    ]);
    $cryptoProvider = InvestmentProvider::factory()->crypto()->create();

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

    SavingsAccount::factory()->create([
        'amount' => '1500.00',
    ]);
    InvestmentPurchase::factory()->create([
        'investment_symbol_id' => $bond->id,
        'quantity' => '2.00000000',
        'price_per_unit' => '95.00',
        'fee' => '0.00',
    ]);
    InvestmentPurchase::factory()->create([
        'investment_symbol_id' => $vwce->id,
        'quantity' => '5.00000000',
        'price_per_unit' => '90.00',
        'fee' => '0.00',
    ]);
    InvestmentPurchase::factory()->create([
        'investment_symbol_id' => $krka->id,
        'quantity' => '3.00000000',
        'price_per_unit' => '110.00',
        'fee' => '0.00',
    ]);
    CryptoBalance::factory()->create([
        'investment_provider_id' => $cryptoProvider->id,
        'investment_symbol_id' => $btc->id,
        'manual_quantity' => '2.00000000',
    ]);

    $response = $this->actingAs($user)
        ->get(route('statistics.monthly-summary'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Statistika/MesecniPovzetek')
            ->has('rows', 2)
            ->has('summary_cards', 6)
            ->where('rows.0.total_amount', '2000.00')
            ->where('rows.1.diff_amount', '200.00')
            ->where('rows.1.diff_percentage', '10.00')
            ->where('latest.month_date', '2025-02-01')
            ->has('chartSeries', 6)
        );

    $cards = collect($response->inertiaProps('summary_cards'))->keyBy('key');

    expect($cards->get('savings_amount'))->toMatchArray([
        'current_amount' => '1500.00',
        'diff_amount' => '400.00',
        'diff_percentage' => '36.36',
        'tone' => 'positive',
        'comparison_label' => 'Primerjava z vnosom za 1. 2. 2025.',
    ]);

    expect($cards->get('stock_amount'))->toMatchArray([
        'current_amount' => '300.00',
        'diff_amount' => '-100.00',
        'diff_percentage' => '-25.00',
        'tone' => 'negative',
    ]);

    expect($cards->get('total_amount'))->toMatchArray([
        'current_amount' => '2900.00',
        'diff_amount' => '700.00',
        'diff_percentage' => '31.82',
        'tone' => 'positive',
    ]);
});

test('monthly summary cards show negative diff when current state is below latest snapshot', function () {
    $user = User::factory()->create();

    MonthlyPortfolioSnapshot::factory()->create([
        'month_date' => '2025-02-01',
        'savings_amount' => '500.00',
        'bond_amount' => '0.00',
        'etf_amount' => '0.00',
        'crypto_amount' => '0.00',
        'stock_amount' => '0.00',
        'total_amount' => '500.00',
        'source' => MonthlyPortfolioSnapshot::SOURCE_SCHEDULED,
    ]);

    SavingsAccount::factory()->create([
        'amount' => '300.00',
    ]);

    $response = $this->actingAs($user)
        ->get(route('statistics.monthly-summary'))
        ->assertOk();

    $cards = collect($response->inertiaProps('summary_cards'))->keyBy('key');

    expect($cards->get('savings_amount'))->toMatchArray([
        'current_amount' => '300.00',
        'diff_amount' => '-200.00',
        'diff_percentage' => '-40.00',
        'tone' => 'negative',
    ]);

    expect($cards->get('total_amount'))->toMatchArray([
        'current_amount' => '300.00',
        'diff_amount' => '-200.00',
        'diff_percentage' => '-40.00',
        'tone' => 'negative',
    ]);
});

test('monthly summary cards show warning state when there is no saved snapshot', function () {
    $user = User::factory()->create();

    SavingsAccount::factory()->create([
        'amount' => '250.00',
    ]);

    $response = $this->actingAs($user)
        ->get(route('statistics.monthly-summary'))
        ->assertOk();

    $cards = collect($response->inertiaProps('summary_cards'))->keyBy('key');

    expect($cards->get('savings_amount'))->toMatchArray([
        'current_amount' => '250.00',
        'diff_amount' => null,
        'diff_percentage' => null,
        'tone' => 'warning',
        'comparison_label' => 'Ni shranjenega mesečnega vnosa za primerjavo.',
    ]);

    expect($cards->get('total_amount'))->toMatchArray([
        'current_amount' => '250.00',
        'diff_amount' => null,
        'diff_percentage' => null,
        'tone' => 'warning',
    ]);
});

test('monthly summary cards keep percentage empty when latest snapshot bucket is zero', function () {
    $user = User::factory()->create();

    MonthlyPortfolioSnapshot::factory()->create([
        'month_date' => '2025-02-01',
        'savings_amount' => '0.00',
        'bond_amount' => '0.00',
        'etf_amount' => '0.00',
        'crypto_amount' => '0.00',
        'stock_amount' => '0.00',
        'total_amount' => '0.00',
        'source' => MonthlyPortfolioSnapshot::SOURCE_SCHEDULED,
    ]);

    SavingsAccount::factory()->create([
        'amount' => '100.00',
    ]);

    $response = $this->actingAs($user)
        ->get(route('statistics.monthly-summary'))
        ->assertOk();

    $cards = collect($response->inertiaProps('summary_cards'))->keyBy('key');

    expect($cards->get('savings_amount'))->toMatchArray([
        'current_amount' => '100.00',
        'diff_amount' => '100.00',
        'diff_percentage' => null,
        'tone' => 'positive',
    ]);

    expect($cards->get('total_amount'))->toMatchArray([
        'current_amount' => '100.00',
        'diff_amount' => '100.00',
        'diff_percentage' => null,
        'tone' => 'positive',
    ]);
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

test('authenticated user can view paycheck growth page', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::create(2026, 4, 13, 12, 0, 0, 'Europe/Ljubljana'));

    $user = User::factory()->create();
    $ana = Person::factory()->create([
        'slug' => 'ana',
        'name' => 'Ana',
        'sort_order' => 1,
        'is_active' => true,
    ]);
    $bor = Person::factory()->create([
        'slug' => 'bor',
        'name' => 'Bor',
        'sort_order' => 2,
        'is_active' => true,
    ]);
    Person::factory()->create([
        'slug' => 'neaktivna',
        'name' => 'Neaktivna',
        'sort_order' => 3,
        'is_active' => false,
    ]);

    $ana2024 = PaycheckYear::factory()->create(['person_id' => $ana->id, 'year' => 2024]);
    $bor2024 = PaycheckYear::factory()->create(['person_id' => $bor->id, 'year' => 2024]);
    $ana2025 = PaycheckYear::factory()->create(['person_id' => $ana->id, 'year' => 2025]);
    $bor2025 = PaycheckYear::factory()->create(['person_id' => $bor->id, 'year' => 2025]);
    $ana2026 = PaycheckYear::factory()->create(['person_id' => $ana->id, 'year' => 2026]);

    foreach (range(1, 12) as $month) {
        Paycheck::factory()->create([
            'paycheck_year_id' => $ana2024->id,
            'month' => $month,
            'net' => '2000.00',
            'gross' => '3000.00',
        ]);
        Paycheck::factory()->create([
            'paycheck_year_id' => $bor2024->id,
            'month' => $month,
            'net' => '1500.00',
            'gross' => '2000.00',
        ]);
        Paycheck::factory()->create([
            'paycheck_year_id' => $ana2025->id,
            'month' => $month,
            'net' => '2100.00',
            'gross' => '3200.00',
        ]);
        Paycheck::factory()->create([
            'paycheck_year_id' => $bor2025->id,
            'month' => $month,
            'net' => '1600.00',
            'gross' => '2100.00',
        ]);
    }

    foreach (range(1, 3) as $month) {
        Paycheck::factory()->create([
            'paycheck_year_id' => $ana2026->id,
            'month' => $month,
            'net' => '2200.00',
            'gross' => '3300.00',
        ]);
    }

    Bonus::factory()->create([
        'paycheck_year_id' => $ana2024->id,
        'amount' => '1000.00',
        'taxable' => true,
        'paid_tax' => '250.00',
    ]);
    Bonus::factory()->create([
        'paycheck_year_id' => $ana2024->id,
        'amount' => '500.00',
        'taxable' => false,
        'paid_tax' => '0.00',
    ]);
    Bonus::factory()->create([
        'paycheck_year_id' => $ana2025->id,
        'amount' => '1200.00',
        'taxable' => true,
        'paid_tax' => '300.00',
    ]);
    Bonus::factory()->create([
        'paycheck_year_id' => $bor2025->id,
        'amount' => '400.00',
        'taxable' => false,
        'paid_tax' => '0.00',
    ]);

    $this->actingAs($user)
        ->get(route('statistics.paycheck-growth'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Statistika/RastPlac')
            ->has('filters', 3)
            ->where('filters.0.value', 'all')
            ->where('selectedPerson', 'all')
            ->where('includeBonusesDefault', false)
            ->has('rows', 3)
            ->where('rows.0.year', 2024)
            ->where('rows.0.net', '42000.00')
            ->where('rows.0.gross', '60000.00')
            ->where('rows.0.bonuses_gross', '1500.00')
            ->where('rows.0.bonuses_net', '1250.00')
            ->where('rows.0.recorded_through_month', 12)
            ->where('rows.1.gross_with_bonuses', '65200.00')
            ->where('rows.1.net_with_bonuses', '45700.00')
            ->where('rows.2.is_partial', true)
            ->where('rows.2.recorded_through_month', 3)
            ->has('chartSeries', 4)
            ->where('summary.latest_year', 2026)
            ->where('summary.previous_year', 2025)
            ->where('summary.net_change_amount', '-4500.00')
            ->where('summary.gross_change_amount', '-6000.00')
        );

    CarbonImmutable::setTestNow();
});

test('paycheck growth page can be filtered by person and invalid person falls back to all', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::create(2026, 4, 13, 12, 0, 0, 'Europe/Ljubljana'));

    $user = User::factory()->create();
    $ana = Person::factory()->create([
        'slug' => 'ana',
        'name' => 'Ana',
        'sort_order' => 1,
        'is_active' => true,
    ]);
    $bor = Person::factory()->create([
        'slug' => 'bor',
        'name' => 'Bor',
        'sort_order' => 2,
        'is_active' => true,
    ]);

    $ana2025 = PaycheckYear::factory()->create(['person_id' => $ana->id, 'year' => 2025]);
    $bor2025 = PaycheckYear::factory()->create(['person_id' => $bor->id, 'year' => 2025]);
    $ana2026 = PaycheckYear::factory()->create(['person_id' => $ana->id, 'year' => 2026]);

    foreach (range(1, 12) as $month) {
        Paycheck::factory()->create([
            'paycheck_year_id' => $ana2025->id,
            'month' => $month,
            'net' => '1000.00',
            'gross' => '1500.00',
        ]);
        Paycheck::factory()->create([
            'paycheck_year_id' => $bor2025->id,
            'month' => $month,
            'net' => '500.00',
            'gross' => '700.00',
        ]);
    }

    foreach (range(1, 2) as $month) {
        Paycheck::factory()->create([
            'paycheck_year_id' => $ana2026->id,
            'month' => $month,
            'net' => '1100.00',
            'gross' => '1600.00',
        ]);
    }

    Bonus::factory()->create([
        'paycheck_year_id' => $ana2025->id,
        'amount' => '600.00',
        'taxable' => true,
        'paid_tax' => '100.00',
    ]);
    Bonus::factory()->create([
        'paycheck_year_id' => $bor2025->id,
        'amount' => '200.00',
        'taxable' => false,
        'paid_tax' => '0.00',
    ]);

    $this->actingAs($user)
        ->get(route('statistics.paycheck-growth', ['person' => $ana->slug]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Statistika/RastPlac')
            ->where('selectedPerson', 'ana')
            ->has('rows', 2)
            ->where('rows.0.gross', '18000.00')
            ->where('rows.0.net', '12000.00')
            ->where('rows.0.bonuses_gross', '600.00')
            ->where('rows.0.bonuses_net', '500.00')
            ->where('rows.1.is_partial', true)
            ->where('summary.net_change_amount', '200.00')
            ->where('summary.gross_change_amount', '200.00')
        );

    $this->actingAs($user)
        ->get(route('statistics.paycheck-growth', ['person' => 'neobstojec']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Statistika/RastPlac')
            ->where('selectedPerson', 'all')
            ->where('rows.0.gross', '26400.00')
            ->where('rows.0.bonuses_gross', '800.00')
            ->where('rows.0.bonuses_net', '700.00')
        );

    CarbonImmutable::setTestNow();
});
