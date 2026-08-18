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
            ->where('netWorth.current_total', '0.00')
            ->where('snapshotChange.available', false)
            ->where('snapshotChange.diff_amount', null)
            ->where('snapshotChange.segments', [])
            ->has('allocation', 5)
            ->where('allocation.0.amount', '0.00')
            ->where('allocation.0.month_diff_amount', null)
            ->where('income.latest_full_month', null)
            ->where('income.monthly_interest', '0.00')
            ->where('longView.available', false)
            ->where('longView.consecutive_growth_months', 0)
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

test('dashboard uses live totals, compares the last two snapshots, and skips incomplete current month income', function () {
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
            ->where('netWorth.current_total', '7450.00')
            ->where('snapshotChange.available', true)
            ->where('snapshotChange.snapshot_month_label', 'marec 2026')
            ->where('snapshotChange.snapshot_total', '7000.00')
            ->where('snapshotChange.current_total', '7450.00')
            ->where('snapshotChange.diff_amount', '450.00')
            ->where('snapshotChange.diff_percentage', '6.43')
            ->where('snapshotChange.segments.0.key', 'savings')
            ->where('snapshotChange.segments.0.amount', '0.00')
            ->where('snapshotChange.segments.1.key', 'contribution')
            ->where('snapshotChange.segments.1.amount', '0.00')
            ->where('snapshotChange.segments.2.key', 'market')
            ->where('snapshotChange.segments.2.amount', '450.00')
            ->where('allocation.0.label', 'Varčevanje')
            ->where('allocation.0.amount', '5000.00')
            ->where('allocation.0.month_diff_amount', '200.00')
            ->where('allocation.1.label', 'Kripto')
            ->where('allocation.1.amount', '1250.00')
            ->where('allocation.2.label', 'ETF')
            ->where('allocation.2.amount', '1200.00')
            ->where('income.latest_full_month.month_key', '2026-03')
            ->where('income.latest_full_month.total_net', '3500.00')
            ->where('income.monthly_interest', '7.50')
            ->where('income.current_month.month_key', '2026-04')
            ->where('income.current_month.entered_people_count', 1)
            ->where('income.current_month.expected_people_count', 2)
            ->where('income.current_month.is_complete', false)
            ->where('longView.months', 1)
            ->where('longView.growth_amount', '200.00')
            ->where('longView.average_monthly_growth', '200.00')
            ->where('longView.consecutive_growth_months', 1)
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

test('dashboard investment summary uses signed crypto sell totals', function () {
    $user = User::factory()->create();
    $provider = InvestmentProvider::factory()->crypto('nexo', 'Nexo')->create();
    $btc = InvestmentSymbol::factory()->crypto('BTC')->create([
        'current_price' => '25000.00',
    ]);

    InvestmentPurchase::factory()->create([
        'investment_provider_id' => $provider->id,
        'investment_symbol_id' => $btc->id,
        'quantity' => '0.04000000',
        'price_per_unit' => '20000.00',
        'fee' => '5.00',
    ]);
    InvestmentPurchase::factory()->create([
        'investment_provider_id' => $provider->id,
        'investment_symbol_id' => $btc->id,
        'transaction_type' => 'sell',
        'quantity' => '0.01000000',
        'price_per_unit' => '30000.00',
        'fee' => '3.00',
    ]);

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->loadDeferredProps(['investments'], fn (Assert $reload) => $reload
                ->has('investments')
                ->where('investments.summary.total_invested', '500.00')
                ->where('investments.summary.current_value', '750.00')
                ->where('investments.summary.profit_loss', '242.00')
                ->where('investments.summary.profit_loss_after_tax', '242.00')
                ->has('investments.top_positions', 1)
                ->where('investments.top_positions.0.symbol', 'BTC')
                ->where('investments.top_positions.0.quantity', '0.03000000')
                ->where('investments.top_positions.0.total_invested', '500.00')
                ->where('investments.top_positions.0.current_value', '750.00')
                ->where('investments.top_positions.0.profit_loss', '242.00')
            )
        );
});

test('dashboard splits the change since the last snapshot into savings, contributions and market movement', function () {
    $user = User::factory()->create();
    $person = Person::factory()->create([
        'name' => 'Ana',
        'slug' => 'ana',
        'is_active' => true,
    ]);

    SavingsAccount::factory()->create([
        'person_id' => $person->id,
        'amount' => '1400.00',
        'apy' => '0.00',
        'sort_order' => 1,
    ]);

    $ibkr = InvestmentProvider::factory()->ibkr()->create();
    $vwce = InvestmentSymbol::factory()->create([
        'type' => InvestmentSymbolType::ETF,
        'symbol' => 'VWCE',
        'taxable' => false,
        'current_price' => '112.00',
    ]);

    InvestmentPurchase::factory()->create([
        'investment_provider_id' => $ibkr->id,
        'investment_symbol_id' => $vwce->id,
        'purchased_at' => '2026-02-15 09:00:00',
        'quantity' => '5.00000000',
        'price_per_unit' => '100.00',
        'fee' => '0.00',
    ]);
    InvestmentPurchase::factory()->create([
        'investment_provider_id' => $ibkr->id,
        'investment_symbol_id' => $vwce->id,
        'purchased_at' => '2026-03-20 09:00:00',
        'quantity' => '2.00000000',
        'price_per_unit' => '110.00',
        'fee' => '0.00',
    ]);

    foreach ([
        ['2026-01-01', '1000.00', '0.00', '1000.00'],
        ['2026-02-01', '1100.00', '0.00', '1100.00'],
        ['2026-03-01', '1250.00', '560.00', '1810.00'],
    ] as [$monthDate, $savings, $etf, $total]) {
        MonthlyPortfolioSnapshot::factory()->create([
            'month_date' => $monthDate,
            'savings_amount' => $savings,
            'bond_amount' => '0.00',
            'etf_amount' => $etf,
            'crypto_amount' => '0.00',
            'stock_amount' => '0.00',
            'total_amount' => $total,
            'source' => MonthlyPortfolioSnapshot::SOURCE_SCHEDULED,
        ]);
    }

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('netWorth.current_total', '2184.00')
            ->where('snapshotChange.snapshot_month_label', 'marec 2026')
            ->where('snapshotChange.snapshot_total', '1810.00')
            ->where('snapshotChange.current_total', '2184.00')
            ->where('snapshotChange.diff_amount', '374.00')
            ->where('snapshotChange.diff_percentage', '20.66')
            ->has('snapshotChange.segments', 3)
            ->where('snapshotChange.segments.0.key', 'savings')
            ->where('snapshotChange.segments.0.amount', '150.00')
            ->where('snapshotChange.segments.0.share_percentage', 40.11)
            ->where('snapshotChange.segments.1.key', 'contribution')
            ->where('snapshotChange.segments.1.amount', '220.00')
            ->where('snapshotChange.segments.1.share_percentage', 58.82)
            ->where('snapshotChange.segments.2.key', 'market')
            ->where('snapshotChange.segments.2.amount', '4.00')
            ->where('snapshotChange.segments.2.share_percentage', 1.07)
            ->where('allocation.0.label', 'Varčevanje')
            ->where('allocation.0.amount', '1400.00')
            ->where('allocation.0.month_diff_amount', '150.00')
            ->where('allocation.1.label', 'ETF')
            ->where('allocation.1.amount', '784.00')
            ->where('allocation.1.month_diff_amount', '560.00')
            ->where('longView.months', 2)
            ->where('longView.growth_amount', '810.00')
            ->where('longView.growth_percentage', '81.00')
            ->where('longView.average_monthly_growth', '405.00')
            ->where('longView.consecutive_growth_months', 2)
        );
});
