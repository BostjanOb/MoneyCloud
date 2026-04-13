<?php

namespace Database\Seeders;

use App\Enums\InvestmentPriceSource;
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
use App\Models\TaxSetting;
use App\Models\User;
use App\Services\MonthlyPortfolioSnapshotService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoSeeder extends Seeder
{
    public function run(MonthlyPortfolioSnapshotService $monthlyPortfolioSnapshotService): void
    {
        $this->seedTaxSettings();
        $this->seedDemoUser();

        $people = $this->seedPeople();
        $savingsAccounts = $this->seedSavingsAccounts($people);
        $providers = $this->seedProviders($savingsAccounts);
        $symbols = $this->seedSymbols();

        $this->seedPaychecks($people);
        $this->seedNonCryptoPurchases($providers, $symbols);
        $this->seedCryptoPortfolio($providers, $symbols);
        $this->seedMonthlySnapshots($monthlyPortfolioSnapshotService);
    }

    private function seedTaxSettings(): void
    {
        if (TaxSetting::query()->doesntExist()) {
            $this->call(TaxSettingSeeder::class);
        }
    }

    private function seedDemoUser(): void
    {
        User::query()->updateOrCreate(
            ['email' => 'demo@example.com'],
            [
                'name' => 'Demo Uporabnik',
                'email_verified_at' => now(),
                'password' => Hash::make('demo1234'),
            ],
        );
    }

    /** @return array<string, Person> */
    private function seedPeople(): array
    {
        $people = [];

        foreach ([
            [
                'slug' => 'maja-kranjc',
                'name' => 'Maja Kranjc',
                'sort_order' => 1,
            ],
            [
                'slug' => 'luka-zupan',
                'name' => 'Luka Zupan',
                'sort_order' => 2,
            ],
        ] as $attributes) {
            $people[$attributes['slug']] = Person::query()->updateOrCreate(
                ['slug' => $attributes['slug']],
                [
                    'name' => $attributes['name'],
                    'is_active' => true,
                    'sort_order' => $attributes['sort_order'],
                ],
            );
        }

        return $people;
    }

    /**
     * @param  array<string, Person>  $people
     * @return array<string, SavingsAccount>
     */
    private function seedSavingsAccounts(array $people): array
    {
        $maja = $people['maja-kranjc'];
        $luka = $people['luka-zupan'];

        $n26Savings = SavingsAccount::query()->updateOrCreate(
            [
                'person_id' => $maja->id,
                'parent_id' => null,
                'name' => 'N26 Savings',
            ],
            [
                'amount' => '7420.00',
                'apy' => '2.10',
                'sort_order' => 1,
            ],
        );

        $revolutSavings = SavingsAccount::query()->updateOrCreate(
            [
                'person_id' => $maja->id,
                'parent_id' => null,
                'name' => 'Revolut Instant savings',
            ],
            [
                'amount' => '0.00',
                'apy' => '2.55',
                'sort_order' => 2,
            ],
        );

        $childAccounts = [];

        foreach ([
            [
                'name' => 'Otrok 1 - Eva',
                'amount' => '1280.00',
                'sort_order' => 1,
            ],
            [
                'name' => 'Otrok 2 - Miha',
                'amount' => '940.00',
                'sort_order' => 2,
            ],
            [
                'name' => 'Otrok 3 - Neža',
                'amount' => '1435.00',
                'sort_order' => 3,
            ],
        ] as $attributes) {
            $childAccounts[] = SavingsAccount::query()->updateOrCreate(
                [
                    'person_id' => $maja->id,
                    'parent_id' => $revolutSavings->id,
                    'name' => $attributes['name'],
                ],
                [
                    'amount' => $attributes['amount'],
                    'apy' => '2.55',
                    'sort_order' => $attributes['sort_order'],
                ],
            );
        }

        $revolutSavings->syncAmountFromChildren();
        $revolutSavings = $revolutSavings->fresh();

        $tradeRepublicSavings = SavingsAccount::query()->updateOrCreate(
            [
                'person_id' => $luka->id,
                'parent_id' => null,
                'name' => 'TradeRepublic',
            ],
            [
                'amount' => '3180.00',
                'apy' => '2.75',
                'sort_order' => 1,
            ],
        );

        return [
            'n26-savings' => $n26Savings,
            'revolut-instant-savings' => $revolutSavings,
            'revolut-child-1' => $childAccounts[0],
            'revolut-child-2' => $childAccounts[1],
            'revolut-child-3' => $childAccounts[2],
            'trade-republic-savings' => $tradeRepublicSavings,
        ];
    }

    /**
     * @param  array<string, SavingsAccount>  $savingsAccounts
     * @return array<string, InvestmentProvider>
     */
    private function seedProviders(array $savingsAccounts): array
    {
        $providers = [];

        foreach ([
            [
                'slug' => 'ibkr',
                'name' => 'IBKR',
                'sort_order' => 1,
                'linked_savings_account_id' => $savingsAccounts['n26-savings']->id,
                'requires_linked_savings_account' => true,
                'supported_symbol_types' => [
                    InvestmentSymbolType::ETF->value,
                    InvestmentSymbolType::STOCK->value,
                ],
            ],
            [
                'slug' => 'ilirika',
                'name' => 'Ilirika',
                'sort_order' => 2,
                'linked_savings_account_id' => null,
                'requires_linked_savings_account' => false,
                'supported_symbol_types' => [
                    InvestmentSymbolType::STOCK->value,
                    InvestmentSymbolType::BOND->value,
                ],
            ],
            [
                'slug' => 'trade-republic',
                'name' => 'TradeRepublic',
                'sort_order' => 3,
                'linked_savings_account_id' => $savingsAccounts['trade-republic-savings']->id,
                'requires_linked_savings_account' => true,
                'supported_symbol_types' => [
                    InvestmentSymbolType::ETF->value,
                ],
            ],
            [
                'slug' => 'binance',
                'name' => 'Binance',
                'sort_order' => 4,
                'linked_savings_account_id' => null,
                'requires_linked_savings_account' => false,
                'supported_symbol_types' => [
                    InvestmentSymbolType::CRYPTO->value,
                ],
            ],
            [
                'slug' => 'bitstamp',
                'name' => 'BitStamp',
                'sort_order' => 5,
                'linked_savings_account_id' => null,
                'requires_linked_savings_account' => false,
                'supported_symbol_types' => [
                    InvestmentSymbolType::CRYPTO->value,
                ],
            ],
            [
                'slug' => 'kraken',
                'name' => 'Kraken',
                'sort_order' => 6,
                'linked_savings_account_id' => null,
                'requires_linked_savings_account' => false,
                'supported_symbol_types' => [
                    InvestmentSymbolType::CRYPTO->value,
                ],
            ],
        ] as $attributes) {
            $providers[$attributes['slug']] = InvestmentProvider::query()->updateOrCreate(
                ['slug' => $attributes['slug']],
                [
                    'name' => $attributes['name'],
                    'linked_savings_account_id' => $attributes['linked_savings_account_id'],
                    'requires_linked_savings_account' => $attributes['requires_linked_savings_account'],
                    'supported_symbol_types' => $attributes['supported_symbol_types'],
                    'sort_order' => $attributes['sort_order'],
                ],
            );
        }

        return $providers;
    }

    /** @return array<string, InvestmentSymbol> */
    private function seedSymbols(): array
    {
        $symbols = [];

        foreach ([
            [
                'type' => InvestmentSymbolType::ETF,
                'symbol' => 'VWCE',
                'isin' => 'IE00BK5BQT80',
                'taxable' => false,
                'price_source' => InvestmentPriceSource::YFAPI,
                'external_source_id' => 'VWCE.DE',
                'current_price' => '132.45',
            ],
            [
                'type' => InvestmentSymbolType::ETF,
                'symbol' => 'SXR8',
                'isin' => 'IE00B5BMR087',
                'taxable' => false,
                'price_source' => InvestmentPriceSource::YFAPI,
                'external_source_id' => 'SXR8.DE',
                'current_price' => '556.20',
            ],
            [
                'type' => InvestmentSymbolType::BOND,
                'symbol' => 'RS94',
                'isin' => null,
                'taxable' => false,
                'price_source' => InvestmentPriceSource::LJSE,
                'external_source_id' => 'RS94',
                'current_price' => '1015.00',
            ],
            [
                'type' => InvestmentSymbolType::BOND,
                'symbol' => 'RS96',
                'isin' => null,
                'taxable' => false,
                'price_source' => InvestmentPriceSource::LJSE,
                'external_source_id' => 'RS96',
                'current_price' => '1032.50',
            ],
            [
                'type' => InvestmentSymbolType::STOCK,
                'symbol' => 'KRKG',
                'isin' => 'SI0031102120',
                'taxable' => true,
                'price_source' => InvestmentPriceSource::LJSE,
                'external_source_id' => 'KRKG',
                'current_price' => '174.50',
            ],
            [
                'type' => InvestmentSymbolType::STOCK,
                'symbol' => 'PETG',
                'isin' => 'SI0031102153',
                'taxable' => true,
                'price_source' => InvestmentPriceSource::LJSE,
                'external_source_id' => 'PETG',
                'current_price' => '38.70',
            ],
            [
                'type' => InvestmentSymbolType::STOCK,
                'symbol' => 'AAPL',
                'isin' => 'US0378331005',
                'taxable' => true,
                'price_source' => InvestmentPriceSource::YFAPI,
                'external_source_id' => 'AAPL',
                'current_price' => '198.35',
            ],
            [
                'type' => InvestmentSymbolType::CRYPTO,
                'symbol' => 'BTC',
                'isin' => null,
                'taxable' => false,
                'price_source' => InvestmentPriceSource::COINMARKETCAP,
                'external_source_id' => '1',
                'current_price' => '82450.00',
            ],
            [
                'type' => InvestmentSymbolType::CRYPTO,
                'symbol' => 'ETH',
                'isin' => null,
                'taxable' => false,
                'price_source' => InvestmentPriceSource::COINMARKETCAP,
                'external_source_id' => '1027',
                'current_price' => '3980.00',
            ],
            [
                'type' => InvestmentSymbolType::CRYPTO,
                'symbol' => 'BNB',
                'isin' => null,
                'taxable' => false,
                'price_source' => InvestmentPriceSource::COINMARKETCAP,
                'external_source_id' => '1839',
                'current_price' => '612.00',
            ],
        ] as $attributes) {
            $symbols[$attributes['symbol']] = InvestmentSymbol::query()->updateOrCreate(
                [
                    'type' => $attributes['type']->value,
                    'symbol' => $attributes['symbol'],
                ],
                [
                    'isin' => $attributes['isin'],
                    'taxable' => $attributes['taxable'],
                    'price_source' => $attributes['price_source']->value,
                    'external_source_id' => $this->normalizeExternalSourceId(
                        $attributes['price_source'],
                        $attributes['external_source_id'],
                    ),
                    'current_price' => $attributes['current_price'],
                    'price_synced_at' => now(),
                ],
            );
        }

        return $symbols;
    }

    /** @param  array<string, Person>  $people */
    private function seedPaychecks(array $people): void
    {
        $currentDate = CarbonImmutable::now('Europe/Ljubljana');

        foreach ([
            [
                'slug' => 'maja-kranjc',
                'base_gross' => 3180,
                'yearly_raise' => 185,
                'gross_wave' => 75,
                'contribution_rate' => 0.232,
                'tax_rate' => 0.099,
                'child_months' => [12, 12, 0],
            ],
            [
                'slug' => 'luka-zupan',
                'base_gross' => 2470,
                'yearly_raise' => 155,
                'gross_wave' => 55,
                'contribution_rate' => 0.228,
                'tax_rate' => 0.092,
                'child_months' => [0, 0, 0],
            ],
        ] as $profile) {
            $person = $people[$profile['slug']];

            for ($year = 2023; $year <= $currentDate->year; $year++) {
                $paycheckYear = PaycheckYear::query()->updateOrCreate(
                    [
                        'person_id' => $person->id,
                        'year' => $year,
                    ],
                    [
                        'child1_months' => $profile['child_months'][0],
                        'child2_months' => $profile['child_months'][1],
                        'child3_months' => $profile['child_months'][2],
                    ],
                );

                $lastMonth = $year === $currentDate->year ? $currentDate->month : 12;

                for ($month = 1; $month <= $lastMonth; $month++) {
                    $gross = round(
                        $profile['base_gross']
                        + (($year - 2023) * $profile['yearly_raise'])
                        + (($month % 4) * 22)
                        + (((($month + $year) % 5) - 2) * $profile['gross_wave']),
                        2,
                    );

                    $contributionRate = $profile['contribution_rate']
                        + (($month % 3) * 0.0025)
                        + (($year - 2023) * 0.0015);
                    $taxRate = $profile['tax_rate']
                        + (($month % 4) * 0.0030)
                        + (($year - 2023) * 0.0020);

                    $contributions = round($gross * $contributionRate, 2);
                    $taxes = round($gross * $taxRate, 2);
                    $net = round($gross - $contributions - $taxes, 2);

                    Paycheck::query()->updateOrCreate(
                        [
                            'paycheck_year_id' => $paycheckYear->id,
                            'month' => $month,
                        ],
                        [
                            'gross' => number_format($gross, 2, '.', ''),
                            'contributions' => number_format($contributions, 2, '.', ''),
                            'taxes' => number_format($taxes, 2, '.', ''),
                            'net' => number_format($net, 2, '.', ''),
                        ],
                    );
                }
            }
        }
    }

    /**
     * @param  array<string, InvestmentProvider>  $providers
     * @param  array<string, InvestmentSymbol>  $symbols
     */
    private function seedNonCryptoPurchases(array $providers, array $symbols): void
    {
        foreach ([
            ['provider' => 'ibkr', 'symbol' => 'VWCE', 'purchased_at' => '2023-05-15 10:00:00', 'quantity' => '12.00000000', 'price_per_unit' => '96.50', 'fee' => '1.90'],
            ['provider' => 'ibkr', 'symbol' => 'AAPL', 'purchased_at' => '2023-11-22 15:30:00', 'quantity' => '5.00000000', 'price_per_unit' => '187.20', 'fee' => '1.50'],
            ['provider' => 'ibkr', 'symbol' => 'SXR8', 'purchased_at' => '2024-03-12 09:45:00', 'quantity' => '3.00000000', 'price_per_unit' => '438.40', 'fee' => '1.90'],
            ['provider' => 'ibkr', 'symbol' => 'VWCE', 'purchased_at' => '2024-09-18 10:15:00', 'quantity' => '8.00000000', 'price_per_unit' => '112.30', 'fee' => '1.90'],
            ['provider' => 'ibkr', 'symbol' => 'AAPL', 'purchased_at' => '2025-02-10 16:20:00', 'quantity' => '4.00000000', 'price_per_unit' => '182.75', 'fee' => '1.50'],
            ['provider' => 'ibkr', 'symbol' => 'SXR8', 'purchased_at' => '2025-07-09 11:10:00', 'quantity' => '2.00000000', 'price_per_unit' => '501.10', 'fee' => '1.90'],
            ['provider' => 'ibkr', 'symbol' => 'VWCE', 'purchased_at' => '2026-01-16 13:00:00', 'quantity' => '6.00000000', 'price_per_unit' => '128.10', 'fee' => '1.90'],
            ['provider' => 'ilirika', 'symbol' => 'KRKG', 'purchased_at' => '2023-05-08 09:20:00', 'quantity' => '20.00000000', 'price_per_unit' => '118.50', 'fee' => '7.20'],
            ['provider' => 'ilirika', 'symbol' => 'PETG', 'purchased_at' => '2024-04-15 09:10:00', 'quantity' => '35.00000000', 'price_per_unit' => '27.80', 'fee' => '7.20'],
            ['provider' => 'ilirika', 'symbol' => 'KRKG', 'purchased_at' => '2025-08-14 09:05:00', 'quantity' => '10.00000000', 'price_per_unit' => '162.00', 'fee' => '7.20'],
            ['provider' => 'ilirika', 'symbol' => 'RS94', 'purchased_at' => '2025-01-22 08:45:00', 'quantity' => '1.00000000', 'price_per_unit' => '1008.00', 'fee' => '4.50', 'yield' => '3.65', 'coupon_date' => '2025-06-15', 'expiry_date' => '2029-12-01'],
            ['provider' => 'ilirika', 'symbol' => 'RS96', 'purchased_at' => '2025-10-13 08:45:00', 'quantity' => '1.00000000', 'price_per_unit' => '1016.50', 'fee' => '4.50', 'yield' => '3.10', 'coupon_date' => '2026-03-21', 'expiry_date' => '2032-03-21'],
            ['provider' => 'trade-republic', 'symbol' => 'VWCE', 'purchased_at' => '2025-05-06 12:00:00', 'quantity' => '4.00000000', 'price_per_unit' => '118.20', 'fee' => '1.00'],
            ['provider' => 'trade-republic', 'symbol' => 'SXR8', 'purchased_at' => '2025-12-08 12:05:00', 'quantity' => '1.50000000', 'price_per_unit' => '529.40', 'fee' => '1.00'],
        ] as $purchase) {
            InvestmentPurchase::query()->updateOrCreate(
                [
                    'investment_provider_id' => $providers[$purchase['provider']]->id,
                    'investment_symbol_id' => $symbols[$purchase['symbol']]->id,
                    'purchased_at' => $purchase['purchased_at'],
                ],
                [
                    'quantity' => $purchase['quantity'],
                    'price_per_unit' => $purchase['price_per_unit'],
                    'fee' => $purchase['fee'],
                    'yield' => $purchase['yield'] ?? null,
                    'coupon_date' => $purchase['coupon_date'] ?? null,
                    'expiry_date' => $purchase['expiry_date'] ?? null,
                ],
            );
        }
    }

    /**
     * @param  array<string, InvestmentProvider>  $providers
     * @param  array<string, InvestmentSymbol>  $symbols
     */
    private function seedCryptoPortfolio(array $providers, array $symbols): void
    {
        $endMonth = CarbonImmutable::now('Europe/Ljubljana')->startOfMonth();

        $btcQuantity = $this->seedCryptoDcaPurchases(
            provider: $providers['binance'],
            symbol: $symbols['BTC'],
            startMonth: $endMonth->subMonths(23),
            months: 24,
            baseBudget: 120.00,
            budgetStep: 12.50,
            basePrice: 43800.00,
            monthlyPriceIncrease: 1680.00,
            fee: '0.80',
            dayOfMonth: 5,
        );

        $ethQuantity = $this->seedCryptoDcaPurchases(
            provider: $providers['bitstamp'],
            symbol: $symbols['ETH'],
            startMonth: $endMonth->subMonths(14),
            months: 15,
            baseBudget: 95.00,
            budgetStep: 8.00,
            basePrice: 2380.00,
            monthlyPriceIncrease: 118.00,
            fee: '0.65',
            dayOfMonth: 8,
        );

        $this->upsertCryptoBalance($providers['binance'], $symbols['BTC'], $btcQuantity);
        $this->upsertCryptoBalance($providers['bitstamp'], $symbols['ETH'], $ethQuantity);
        $this->upsertCryptoBalance($providers['kraken'], $symbols['BNB'], '3.25000000');
    }

    private function seedCryptoDcaPurchases(
        InvestmentProvider $provider,
        InvestmentSymbol $symbol,
        CarbonImmutable $startMonth,
        int $months,
        float $baseBudget,
        float $budgetStep,
        float $basePrice,
        float $monthlyPriceIncrease,
        string $fee,
        int $dayOfMonth,
    ): string {
        $totalQuantity = 0.0;

        for ($index = 0; $index < $months; $index++) {
            $monthDate = $startMonth->addMonths($index);
            $budget = $baseBudget + (($index % 4) * $budgetStep);
            $price = $basePrice + ($index * $monthlyPriceIncrease) + ((($index % 3) - 1) * 240.00);
            $quantity = $budget / $price;

            InvestmentPurchase::query()->updateOrCreate(
                [
                    'investment_provider_id' => $provider->id,
                    'investment_symbol_id' => $symbol->id,
                    'purchased_at' => $monthDate->setDay($dayOfMonth)->setTime(9, 0, 0)->toDateTimeString(),
                ],
                [
                    'quantity' => $this->formatQuantity($quantity),
                    'price_per_unit' => number_format($price, 2, '.', ''),
                    'fee' => $fee,
                    'yield' => null,
                    'coupon_date' => null,
                    'expiry_date' => null,
                ],
            );

            $totalQuantity += $quantity;
        }

        return $this->formatQuantity($totalQuantity);
    }

    private function upsertCryptoBalance(
        InvestmentProvider $provider,
        InvestmentSymbol $symbol,
        string $quantity,
    ): void {
        CryptoBalance::query()->updateOrCreate(
            [
                'investment_provider_id' => $provider->id,
                'investment_symbol_id' => $symbol->id,
            ],
            [
                'manual_quantity' => $quantity,
            ],
        );
    }

    private function seedMonthlySnapshots(
        MonthlyPortfolioSnapshotService $monthlyPortfolioSnapshotService,
    ): void {
        $endMonth = CarbonImmutable::now('Europe/Ljubljana')->startOfMonth();
        $startMonth = $endMonth->subMonths(23);
        $endingTotals = collect($monthlyPortfolioSnapshotService->currentStateTotals())
            ->mapWithKeys(fn (string $amount, string $key): array => [
                $key => MonthlyPortfolioSnapshot::toCents($amount),
            ])
            ->all();

        $bucketOffsets = [
            'savings_amount' => -0.03,
            'bond_amount' => 0.02,
            'etf_amount' => 0.04,
            'crypto_amount' => 0.06,
            'stock_amount' => 0.03,
        ];

        for ($index = 0; $index < 24; $index++) {
            $monthDate = $startMonth->addMonths($index);
            $isLastMonth = $index === 23;
            $baseFactor = $isLastMonth
                ? 1.0
                : max(0.55, min(0.98, 0.61 + (($index / 23) * 0.33) + (sin($index * 0.65) * 0.02)));

            $payload = [];

            foreach ($bucketOffsets as $key => $offset) {
                $factor = $isLastMonth
                    ? 1.0
                    : max(0.50, min(0.98, $baseFactor + $offset + (sin(($index + 1) * 0.4) * 0.015)));

                $payload[$key] = $isLastMonth
                    ? MonthlyPortfolioSnapshot::fromCents($endingTotals[$key])
                    : MonthlyPortfolioSnapshot::fromCents(
                        (int) round($endingTotals[$key] * $factor),
                    );
            }

            $attributes = [
                ...$payload,
                'month_date' => $monthDate->toDateString(),
                'total_amount' => MonthlyPortfolioSnapshot::fromCents(
                    array_sum(array_map(
                        fn (string $amount): int => MonthlyPortfolioSnapshot::toCents($amount),
                        $payload,
                    )),
                ),
                'source' => MonthlyPortfolioSnapshot::SOURCE_SCHEDULED,
            ];

            $snapshot = MonthlyPortfolioSnapshot::query()
                ->whereDate('month_date', $monthDate->toDateString())
                ->first();

            if ($snapshot instanceof MonthlyPortfolioSnapshot) {
                $snapshot->update($attributes);

                continue;
            }

            MonthlyPortfolioSnapshot::query()->create($attributes);
        }
    }

    private function normalizeExternalSourceId(
        InvestmentPriceSource $priceSource,
        string $externalSourceId,
    ): string {
        return match ($priceSource) {
            InvestmentPriceSource::YFAPI, InvestmentPriceSource::LJSE => mb_strtoupper($externalSourceId),
            default => $externalSourceId,
        };
    }

    private function formatQuantity(float $quantity): string
    {
        return number_format($quantity, 8, '.', '');
    }
}
