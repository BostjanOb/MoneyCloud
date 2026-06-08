<?php

use App\Enums\InvestmentSymbolType;
use App\Enums\InvestmentTransactionType;
use App\Models\CryptoBalance;
use App\Models\InvestmentProvider;
use App\Models\InvestmentPurchase;
use App\Models\InvestmentSymbol;
use App\Models\MonthlyPortfolioSnapshot;
use App\Models\Paycheck;
use App\Models\PaycheckYear;
use App\Models\Person;
use App\Models\SavingsAccount;
use App\Models\TaxSetting;
use App\Services\FinancialContextService;
use Carbon\CarbonImmutable;

function financialContext(): FinancialContextService
{
    return app(FinancialContextService::class);
}

function makeSecurity(InvestmentSymbolType $type, string $symbol, string $currentPrice, string $quantity, string $pricePerUnit, bool $taxable = false): InvestmentPurchase
{
    $investmentSymbol = InvestmentSymbol::factory()->create([
        'type' => $type,
        'symbol' => $symbol,
        'current_price' => $currentPrice,
        'taxable' => $taxable,
    ]);

    return InvestmentPurchase::factory()->create([
        'investment_provider_id' => InvestmentProvider::factory()->create()->id,
        'investment_symbol_id' => $investmentSymbol->id,
        'transaction_type' => InvestmentTransactionType::Buy,
        'quantity' => $quantity,
        'price_per_unit' => $pricePerUnit,
        'fee' => '0.00',
        'purchased_at' => CarbonImmutable::parse('2024-01-15 10:00:00'),
    ]);
}

function makeCryptoBalance(string $symbol, string $currentPrice, string $quantity, ?string $apy = null): CryptoBalance
{
    return CryptoBalance::factory()->create([
        'investment_provider_id' => InvestmentProvider::factory()->crypto()->create()->id,
        'investment_symbol_id' => InvestmentSymbol::factory()->crypto($symbol)->create([
            'current_price' => $currentPrice,
        ])->id,
        'manual_quantity' => $quantity,
        'apy' => $apy,
    ]);
}

function seedStandardHousehold(): void
{
    // Net worth target: 100.000,00 € → savings 50%, etf 20%, stock 10%, bond 5%, crypto 15%.
    SavingsAccount::factory()->create([
        'parent_id' => null,
        'amount' => '50000.00',
        'apy' => '4.00',
    ]);

    makeSecurity(InvestmentSymbolType::ETF, 'VWCE', '100.00', '200.00000000', '80.00'); // 20.000 value, 16.000 cost
    makeSecurity(InvestmentSymbolType::STOCK, 'AAPL', '200.00', '50.00000000', '150.00'); // 10.000
    makeSecurity(InvestmentSymbolType::BOND, 'SI0002', '1000.00', '5.00000000', '1000.00'); // 5.000
    makeCryptoBalance('BTC', '30000.00', '0.50000000'); // 15.000
}

test('net worth overview sums all asset classes', function () {
    seedStandardHousehold();

    $overview = financialContext()->netWorthOverview();

    expect($overview['currency'])->toBe('EUR')
        ->and($overview['total'])->toBe('100000.00');

    $byClass = collect($overview['by_class'])->keyBy('key');

    expect($byClass['savings']['amount'])->toBe('50000.00')
        ->and($byClass['etf']['amount'])->toBe('20000.00')
        ->and($byClass['stock']['amount'])->toBe('10000.00')
        ->and($byClass['bond']['amount'])->toBe('5000.00')
        ->and($byClass['crypto']['amount'])->toBe('15000.00');
});

test('allocation breakdown computes shares and largest class', function () {
    seedStandardHousehold();

    $allocation = financialContext()->allocationBreakdown();

    $shares = collect($allocation['items'])->keyBy('key');

    expect($allocation['total'])->toBe('100000.00')
        ->and($allocation['largest_class'])->toBe('Varčevanje')
        ->and($shares['savings']['share_percentage'])->toBe('50.00')
        ->and($shares['etf']['share_percentage'])->toBe('20.00')
        ->and($shares['stock']['share_percentage'])->toBe('10.00')
        ->and($shares['bond']['share_percentage'])->toBe('5.00')
        ->and($shares['crypto']['share_percentage'])->toBe('15.00');
});

test('allocation breakdown handles empty portfolio', function () {
    $allocation = financialContext()->allocationBreakdown();

    expect($allocation['total'])->toBe('0.00')
        ->and($allocation['largest_class'])->toBeNull()
        ->and(collect($allocation['items'])->firstWhere('key', 'etf')['share_percentage'])->toBe('0.00');
});

test('savings accounts compute interest and weighted apy', function () {
    SavingsAccount::factory()->create(['parent_id' => null, 'amount' => '50000.00', 'apy' => '4.00']);
    SavingsAccount::factory()->create(['parent_id' => null, 'amount' => '30000.00', 'apy' => '6.00']);

    $savings = financialContext()->savingsAccounts();

    // weighted = (50000*4 + 30000*6) / 80000 = 4.75
    expect($savings['totals']['total_amount'])->toBe('80000.00')
        ->and($savings['totals']['weighted_apy'])->toBe('4.75')
        ->and($savings['totals']['total_annual_interest'])->toBe('3800.00') // 2000 + 1800
        ->and($savings['totals']['total_monthly_interest'])->toBe('316.67') // 166.67 + 150.00
        ->and($savings['accounts'])->toHaveCount(2);
});

test('investment holdings compute profit and loss', function () {
    makeSecurity(InvestmentSymbolType::ETF, 'VWCE', '100.00', '200.00000000', '80.00');

    $holdings = financialContext()->investmentHoldings();
    $etf = collect($holdings['securities'])->firstWhere('symbol', 'VWCE');

    expect($etf['total_invested'])->toBe('16000.00')
        ->and($etf['current_value'])->toBe('20000.00')
        ->and($etf['profit_loss'])->toBe('4000.00')
        ->and($etf['return_percentage'])->toBe('25.00')
        ->and($holdings['totals']['current_value'])->toBe('20000.00')
        ->and($holdings['totals']['profit_loss'])->toBe('4000.00');
});

test('investment holdings include crypto by symbol', function () {
    makeCryptoBalance('BTC', '30000.00', '0.50000000', '6.00');

    $holdings = financialContext()->investmentHoldings();
    $btc = collect($holdings['crypto'])->firstWhere('symbol', 'BTC');

    expect($btc['current_value'])->toBe('15000.00')
        ->and($btc['weighted_apy'])->toBe('6.00')
        ->and($btc['annual_interest'])->toBe('900.00')
        ->and($btc['monthly_interest'])->toBe('75.00')
        ->and($btc['balances'][0]['apy'])->toBe('6.00')
        ->and($btc['balances'][0]['annual_interest'])->toBe('900.00')
        ->and($holdings['totals']['crypto_value'])->toBe('15000.00');
});

test('portfolio history computes growth across snapshots', function () {
    foreach ([['2025-01-01', '10000.00'], ['2025-02-01', '12000.00'], ['2025-03-01', '15000.00']] as [$month, $total]) {
        MonthlyPortfolioSnapshot::factory()->create([
            'month_date' => $month,
            'savings_amount' => $total,
            'bond_amount' => '0.00',
            'etf_amount' => '0.00',
            'crypto_amount' => '0.00',
            'stock_amount' => '0.00',
            'total_amount' => $total,
        ]);
    }

    $history = financialContext()->portfolioHistory(24);

    expect($history['months'])->toBe(3)
        ->and($history['start_total'])->toBe('10000.00')
        ->and($history['end_total'])->toBe('15000.00')
        ->and($history['growth_amount'])->toBe('5000.00')
        ->and($history['growth_percentage'])->toBe('50.00')
        ->and($history['points'][1]['change_amount'])->toBe('2000.00')
        ->and($history['points'][1]['change_percentage'])->toBe('20.00');
});

test('income summary aggregates by year with year over year change', function () {
    $personA = Person::factory()->create(['name' => 'Ana']);
    $personB = Person::factory()->create(['name' => 'Bojan']);

    $a2024 = PaycheckYear::factory()->create(['person_id' => $personA->id, 'year' => 2024]);
    $b2024 = PaycheckYear::factory()->create(['person_id' => $personB->id, 'year' => 2024]);
    $a2025 = PaycheckYear::factory()->create(['person_id' => $personA->id, 'year' => 2025]);
    $b2025 = PaycheckYear::factory()->create(['person_id' => $personB->id, 'year' => 2025]);

    Paycheck::factory()->create(['paycheck_year_id' => $a2024->id, 'month' => 1, 'net' => '1000.00', 'gross' => '1500.00']);
    Paycheck::factory()->create(['paycheck_year_id' => $a2024->id, 'month' => 2, 'net' => '1000.00', 'gross' => '1500.00']);
    Paycheck::factory()->create(['paycheck_year_id' => $b2024->id, 'month' => 1, 'net' => '500.00', 'gross' => '700.00']);

    Paycheck::factory()->create(['paycheck_year_id' => $a2025->id, 'month' => 1, 'net' => '1100.00', 'gross' => '1600.00']);
    Paycheck::factory()->create(['paycheck_year_id' => $a2025->id, 'month' => 2, 'net' => '1100.00', 'gross' => '1600.00']);
    Paycheck::factory()->create(['paycheck_year_id' => $b2025->id, 'month' => 1, 'net' => '800.00', 'gross' => '1000.00']);

    $a2025->bonuses()->create(['type' => 'regres', 'amount' => '1000.00', 'taxable' => false, 'paid_tax' => '0.00']);

    $summary = financialContext()->incomeSummary();
    $byYear = collect($summary['by_year'])->keyBy('year');

    expect($summary['active_people'])->toContain('Ana', 'Bojan')
        ->and($byYear[2024]['net'])->toBe('2500.00')
        ->and($byYear[2025]['net'])->toBe('3000.00')
        ->and($byYear[2025]['bonuses_net'])->toBe('1000.00')
        ->and($byYear[2025]['net_with_bonuses'])->toBe('4000.00')
        ->and($byYear[2025]['people_count'])->toBe(2)
        ->and($summary['latest_year_change']['from_year'])->toBe(2024)
        ->and($summary['latest_year_change']['to_year'])->toBe(2025)
        ->and($summary['latest_year_change']['net_change_amount'])->toBe('500.00')
        ->and($summary['latest_year_change']['net_change_percentage'])->toBe('20.00');
});

test('bond schedule lists upcoming coupon and expiry events', function () {
    $provider = InvestmentProvider::factory()->create();
    $symbol = InvestmentSymbol::factory()->bond()->create(['symbol' => 'SI0002']);

    InvestmentPurchase::factory()->create([
        'investment_provider_id' => $provider->id,
        'investment_symbol_id' => $symbol->id,
        'transaction_type' => InvestmentTransactionType::Buy,
        'quantity' => '5.00000000',
        'price_per_unit' => '1000.00',
        'fee' => '0.00',
        'coupon_date' => CarbonImmutable::now('Europe/Ljubljana')->addDays(30)->toDateString(),
        'expiry_date' => CarbonImmutable::now('Europe/Ljubljana')->addYears(2)->toDateString(),
    ]);

    $schedule = financialContext()->bondSchedule();

    expect($schedule['events'])->toHaveCount(2);

    $coupon = collect($schedule['events'])->firstWhere('type', 'coupon');

    expect($coupon['symbol'])->toBe('SI0002')
        ->and($coupon['is_upcoming'])->toBeTrue()
        ->and($coupon['days_until'])->toBeGreaterThan(0);
});

test('tax analysis returns dohodnina per active person', function () {
    TaxSetting::factory()->create();
    $person = Person::factory()->create(['name' => 'Ana']);
    $paycheckYear = PaycheckYear::factory()->create([
        'person_id' => $person->id,
        'year' => 2026,
        'child1_months' => 12,
        'child2_months' => 0,
        'child3_months' => 0,
    ]);

    foreach (range(1, 12) as $month) {
        Paycheck::factory()->create([
            'paycheck_year_id' => $paycheckYear->id,
            'month' => $month,
            'net' => '2000.00',
            'gross' => '3000.00',
            'contributions' => '660.00',
            'taxes' => '300.00',
        ]);
    }

    $analysis = financialContext()->taxAnalysis();

    expect($analysis['people'])->toHaveCount(1);

    $personAnalysis = $analysis['people'][0];

    expect($personAnalysis['person'])->toBe('Ana')
        ->and($personAnalysis['year'])->toBe(2026)
        ->and($personAnalysis['has_tax_settings'])->toBeTrue()
        ->and($personAnalysis['sum_gross'])->toBe('36000.00')
        ->and($personAnalysis['dohodnina'])->toBeNumeric()
        ->and($personAnalysis['projection']['is_final'])->toBeTrue();
});
