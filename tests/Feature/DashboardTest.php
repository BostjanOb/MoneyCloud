<?php

use App\Enums\InvestmentSymbolType;
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
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Carbon::setTestNow('2026-04-11 12:00:00');
});

afterEach(function () {
    Carbon::setTestNow();
});

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));

    $response->assertRedirect(route('login'));
});

test('authenticated users can view empty dashboard states', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('hero.current_total.value', '0.00')
            ->where('hero.snapshot_change.value', null)
            ->where('hero.latest_income.value', null)
            ->where('hero.monthly_interest.value', '0.00')
            ->where('allocation.total_amount', '0.00')
            ->has('alerts', 1)
            ->where('quickActions.1.href', route('people.index'))
            ->missing('trend')
            ->missing('investments')
            ->loadDeferredProps(['trend', 'investments'], fn (Assert $reload) => $reload
                ->has('trend')
                ->where('trend.points', [])
                ->where('trend.latest_snapshot', null)
                ->has('investments')
                ->where('investments.summary.total_invested', '0.00')
                ->where('investments.summary.current_value', '0.00')
                ->where('investments.top_positions', [])
            )
        );
});

test('dashboard uses live totals, compares against last snapshot, and skips incomplete current month income', function () {
    $user = User::factory()->create();
    $ana = Person::factory()->create([
        'name' => 'Ana',
        'slug' => 'ana',
        'sort_order' => 1,
        'is_active' => true,
    ]);
    $borut = Person::factory()->create([
        'name' => 'Borut',
        'slug' => 'borut',
        'sort_order' => 2,
        'is_active' => true,
    ]);

    SavingsAccount::factory()->create([
        'person_id' => $ana->id,
        'amount' => '2000.00',
        'apy' => '3.00',
        'sort_order' => 1,
    ]);
    SavingsAccount::factory()->create([
        'person_id' => $borut->id,
        'amount' => '3000.00',
        'apy' => '1.00',
        'sort_order' => 2,
    ]);

    $ibkr = InvestmentProvider::factory()->ibkr()->create();
    $nexo = InvestmentProvider::factory()->crypto('nexo', 'Nexo')->create();

    $vwce = InvestmentSymbol::factory()->create([
        'type' => InvestmentSymbolType::ETF,
        'symbol' => 'VWCE',
        'taxable' => false,
        'current_price' => '120.00',
    ]);
    $btc = InvestmentSymbol::factory()->crypto('BTC')->create([
        'current_price' => '25000.00',
    ]);

    InvestmentPurchase::factory()->create([
        'investment_provider_id' => $ibkr->id,
        'investment_symbol_id' => $vwce->id,
        'purchased_at' => '2025-10-10 09:00:00',
        'quantity' => '10.00000000',
        'price_per_unit' => '100.00',
        'fee' => '10.00',
    ]);
    InvestmentPurchase::factory()->create([
        'investment_provider_id' => $nexo->id,
        'investment_symbol_id' => $btc->id,
        'purchased_at' => '2025-11-15 09:00:00',
        'quantity' => '0.04000000',
        'price_per_unit' => '20000.00',
        'fee' => '5.00',
    ]);

    CryptoBalance::factory()->create([
        'investment_provider_id' => $nexo->id,
        'investment_symbol_id' => $btc->id,
        'manual_quantity' => '0.05000000',
    ]);

    MonthlyPortfolioSnapshot::factory()->create([
        'month_date' => '2026-02-01',
        'savings_amount' => '4800.00',
        'bond_amount' => '0.00',
        'etf_amount' => '1100.00',
        'crypto_amount' => '900.00',
        'stock_amount' => '0.00',
        'total_amount' => '6800.00',
        'source' => MonthlyPortfolioSnapshot::SOURCE_MANUAL,
    ]);
    MonthlyPortfolioSnapshot::factory()->create([
        'month_date' => '2026-03-01',
        'savings_amount' => '5000.00',
        'bond_amount' => '0.00',
        'etf_amount' => '1100.00',
        'crypto_amount' => '900.00',
        'stock_amount' => '0.00',
        'total_amount' => '7000.00',
        'source' => MonthlyPortfolioSnapshot::SOURCE_SCHEDULED,
    ]);

    $anaYear = PaycheckYear::factory()->create([
        'person_id' => $ana->id,
        'year' => 2026,
    ]);
    $borutYear = PaycheckYear::factory()->create([
        'person_id' => $borut->id,
        'year' => 2026,
    ]);

    Paycheck::factory()->create([
        'paycheck_year_id' => $anaYear->id,
        'month' => 3,
        'net' => '1600.00',
        'gross' => '2400.00',
        'taxes' => '350.00',
        'contributions' => '450.00',
    ]);
    Paycheck::factory()->create([
        'paycheck_year_id' => $borutYear->id,
        'month' => 3,
        'net' => '1900.00',
        'gross' => '2800.00',
        'taxes' => '420.00',
        'contributions' => '480.00',
    ]);
    Paycheck::factory()->create([
        'paycheck_year_id' => $anaYear->id,
        'month' => 4,
        'net' => '1800.00',
        'gross' => '2500.00',
        'taxes' => '380.00',
        'contributions' => '470.00',
    ]);

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('hero.current_total.value', '7450.00')
            ->where('hero.snapshot_change.value', '450.00')
            ->where('hero.snapshot_change.percentage', '6.43')
            ->where('hero.latest_income.value', '3500.00')
            ->where('hero.monthly_interest.value', '7.50')
            ->where('allocation.total_amount', '7450.00')
            ->where('allocation.items.0.amount', '5000.00')
            ->where('allocation.items.2.amount', '1200.00')
            ->where('allocation.items.4.amount', '1250.00')
            ->where('income.latest_full_month.month_key', '2026-03')
            ->where('income.current_month.month_key', '2026-04')
            ->where('income.current_month.entered_people_count', 1)
            ->where('income.current_month.expected_people_count', 2)
            ->where('income.current_month.is_complete', false)
            ->has('alerts', 2)
            ->where('alerts.0.key', 'missing_snapshot')
            ->where('alerts.1.key', 'incomplete_current_month_income')
            ->where('alerts.1.href', route('place.index', ['person' => $ana->slug]))
            ->where('quickActions.1.href', route('place.index', ['person' => $ana->slug]))
            ->missing('trend')
            ->missing('investments')
            ->loadDeferredProps(['trend', 'investments'], fn (Assert $reload) => $reload
                ->has('trend')
                ->where('trend.latest_snapshot.month_date', '2026-03-01')
                ->has('trend.points', 2)
                ->where('trend.points.1.total_amount', '7000.00')
                ->has('investments')
                ->where('investments.summary.total_invested', '1800.00')
                ->where('investments.summary.current_value', '2200.00')
                ->where('investments.summary.profit_loss', '385.00')
                ->where('investments.summary.profit_loss_after_tax', '385.00')
                ->has('investments.top_positions', 2)
                ->where('investments.top_positions.0.symbol', 'VWCE')
                ->where('investments.top_positions.0.current_value', '1200.00')
                ->where('investments.top_positions.1.symbol', 'BTC')
                ->where('investments.top_positions.1.current_value', '1000.00')
            )
        );
});
