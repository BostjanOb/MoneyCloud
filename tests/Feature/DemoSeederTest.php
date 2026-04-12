<?php

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
use Database\Seeders\DemoSeeder;
use Illuminate\Support\Facades\Hash;

test('demo seeder creates the expected dataset and remains idempotent', function () {
    CarbonImmutable::setTestNow('2026-04-12 10:00:00');

    $this->seed(DemoSeeder::class);

    expect(User::query()->count())->toBe(1)
        ->and(Person::query()->count())->toBe(2)
        ->and(InvestmentProvider::query()->count())->toBe(6)
        ->and(InvestmentSymbol::query()->count())->toBe(10)
        ->and(SavingsAccount::query()->count())->toBe(6)
        ->and(PaycheckYear::query()->count())->toBe(8)
        ->and(Paycheck::query()->count())->toBe(80)
        ->and(MonthlyPortfolioSnapshot::query()->count())->toBe(24);

    $demoUser = User::query()->firstWhere('email', 'demo@example.com');
    $vwce = InvestmentSymbol::query()->firstWhere('symbol', 'VWCE');
    $rs94 = InvestmentSymbol::query()->firstWhere('symbol', 'RS94');
    $aapl = InvestmentSymbol::query()->firstWhere('symbol', 'AAPL');
    $btc = InvestmentSymbol::query()->firstWhere('symbol', 'BTC');
    $bnb = InvestmentSymbol::query()->firstWhere('symbol', 'BNB');
    $ibkr = InvestmentProvider::query()->firstWhere('slug', 'ibkr');
    $tradeRepublic = InvestmentProvider::query()->firstWhere('slug', 'trade-republic');
    $n26Savings = SavingsAccount::query()->firstWhere('name', 'N26 Savings');
    $tradeRepublicSavings = SavingsAccount::query()->firstWhere('name', 'TradeRepublic');

    expect($demoUser)->not->toBeNull()
        ->and(Hash::check('demo1234', $demoUser->password))->toBeTrue()
        ->and($vwce?->external_source_id)->toBe('VWCE.DE')
        ->and($rs94?->external_source_id)->toBe('RS94')
        ->and($aapl?->external_source_id)->toBe('AAPL')
        ->and($btc?->external_source_id)->toBe('1')
        ->and($ibkr?->linked_savings_account_id)->toBe($n26Savings?->id)
        ->and($tradeRepublic?->linked_savings_account_id)->toBe($tradeRepublicSavings?->id)
        ->and(CryptoBalance::query()->where('investment_symbol_id', $bnb?->id)->count())->toBe(1)
        ->and(InvestmentPurchase::query()->where('investment_symbol_id', $bnb?->id)->count())->toBe(0);

    $latestSnapshot = MonthlyPortfolioSnapshot::query()->orderByDesc('month_date')->first();

    expect($latestSnapshot?->month_date?->toDateString())->toBe('2026-04-01')
        ->and($latestSnapshot?->source)->toBe(MonthlyPortfolioSnapshot::SOURCE_SCHEDULED);

    $this->seed(DemoSeeder::class);

    expect(User::query()->count())->toBe(1)
        ->and(Person::query()->count())->toBe(2)
        ->and(InvestmentProvider::query()->count())->toBe(6)
        ->and(InvestmentSymbol::query()->count())->toBe(10)
        ->and(SavingsAccount::query()->count())->toBe(6)
        ->and(PaycheckYear::query()->count())->toBe(8)
        ->and(Paycheck::query()->count())->toBe(80)
        ->and(MonthlyPortfolioSnapshot::query()->count())->toBe(24);

    CarbonImmutable::setTestNow();
});
