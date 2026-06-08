<?php

namespace App\Services;

use App\Enums\InvestmentSymbolType;
use App\Models\Bonus;
use App\Models\InvestmentPurchase;
use App\Models\MonthlyPortfolioSnapshot;
use App\Models\PaycheckYear;
use App\Models\Person;
use App\Models\SavingsAccount;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Aggregates the household's financial data into clean, pre-computed numeric
 * structures for consumption by the AI financial advisor.
 *
 * Every figure is calculated deterministically in PHP. The AI only interprets
 * the numbers returned here — it never performs arithmetic itself.
 *
 * Monetary values are returned as decimal strings (e.g. "1234.56") in EUR.
 * Percentages are returned as decimal strings (e.g. "12.50").
 */
class FinancialContextService
{
    /** @var array<string, string> */
    private const CLASS_LABELS = [
        'savings_amount' => 'Varčevanje',
        'bond_amount' => 'Obveznice',
        'etf_amount' => 'ETF',
        'stock_amount' => 'Delnice',
        'crypto_amount' => 'Kripto',
    ];

    public function __construct(
        private readonly InvestmentPortfolioService $investmentPortfolioService,
        private readonly MonthlyPortfolioSnapshotService $monthlyPortfolioSnapshotService,
        private readonly CryptoPortfolioService $cryptoPortfolioService,
        private readonly TaxCalculationService $taxCalculationService,
    ) {}

    /**
     * Current net worth split by asset class.
     *
     * @return array{
     *     currency: string,
     *     total: string,
     *     by_class: array<int, array{key: string, label: string, amount: string}>
     * }
     */
    public function netWorthOverview(): array
    {
        $totals = $this->monthlyPortfolioSnapshotService->currentStateTotals();
        $totalInCents = $this->sumCents($totals);

        $byClass = [];

        foreach (self::CLASS_LABELS as $key => $label) {
            $byClass[] = [
                'key' => str_replace('_amount', '', $key),
                'label' => $label,
                'amount' => $totals[$key] ?? '0.00',
            ];
        }

        return [
            'currency' => 'EUR',
            'total' => MonthlyPortfolioSnapshot::fromCents($totalInCents),
            'by_class' => $byClass,
        ];
    }

    /**
     * Current allocation of net worth by asset class, including each class's share.
     *
     * @return array{
     *     currency: string,
     *     total: string,
     *     largest_class: string|null,
     *     items: array<int, array{key: string, label: string, amount: string, share_percentage: string}>
     * }
     */
    public function allocationBreakdown(): array
    {
        $overview = $this->netWorthOverview();
        $totalInCents = MonthlyPortfolioSnapshot::toCents($overview['total']);

        $items = array_map(function (array $item) use ($totalInCents): array {
            $amountInCents = MonthlyPortfolioSnapshot::toCents($item['amount']);

            return [
                'key' => $item['key'],
                'label' => $item['label'],
                'amount' => $item['amount'],
                'share_percentage' => $totalInCents === 0
                    ? '0.00'
                    : number_format($amountInCents / $totalInCents * 100, 2, '.', ''),
            ];
        }, $overview['by_class']);

        $largest = collect($items)
            ->sortByDesc(fn (array $item): int => MonthlyPortfolioSnapshot::toCents($item['amount']))
            ->first();

        return [
            'currency' => 'EUR',
            'total' => $overview['total'],
            'largest_class' => $totalInCents === 0 ? null : ($largest['label'] ?? null),
            'items' => $items,
        ];
    }

    /**
     * Historical monthly portfolio snapshots over the requested window, with growth.
     *
     * @return array{
     *     currency: string,
     *     from_month: string|null,
     *     to_month: string|null,
     *     months: int,
     *     start_total: string|null,
     *     end_total: string|null,
     *     growth_amount: string|null,
     *     growth_percentage: string|null,
     *     points: array<int, array<string, string|null>>
     * }
     */
    public function portfolioHistory(int $months = 24): array
    {
        $months = max(1, min(120, $months));

        $window = MonthlyPortfolioSnapshot::ordered()->get()->slice(-$months)->values();

        if ($window->isEmpty()) {
            return [
                'currency' => 'EUR',
                'from_month' => null,
                'to_month' => null,
                'months' => 0,
                'start_total' => null,
                'end_total' => null,
                'growth_amount' => null,
                'growth_percentage' => null,
                'points' => [],
            ];
        }

        $points = [];
        $previous = null;

        foreach ($window as $snapshot) {
            $totalInCents = MonthlyPortfolioSnapshot::toCents($snapshot->total_amount);
            $previousTotalInCents = $previous instanceof MonthlyPortfolioSnapshot
                ? MonthlyPortfolioSnapshot::toCents($previous->total_amount)
                : null;
            $changeInCents = $previousTotalInCents === null ? null : $totalInCents - $previousTotalInCents;

            $points[] = [
                'month' => $snapshot->month_date?->toDateString(),
                'savings' => $snapshot->savings_amount,
                'bond' => $snapshot->bond_amount,
                'etf' => $snapshot->etf_amount,
                'stock' => $snapshot->stock_amount,
                'crypto' => $snapshot->crypto_amount,
                'total' => $snapshot->total_amount,
                'change_amount' => $changeInCents === null
                    ? null
                    : MonthlyPortfolioSnapshot::fromCents($changeInCents),
                'change_percentage' => $previousTotalInCents === null || $previousTotalInCents === 0
                    ? null
                    : number_format($changeInCents / $previousTotalInCents * 100, 2, '.', ''),
            ];

            $previous = $snapshot;
        }

        /** @var MonthlyPortfolioSnapshot $first */
        $first = $window->first();
        /** @var MonthlyPortfolioSnapshot $last */
        $last = $window->last();
        $startInCents = MonthlyPortfolioSnapshot::toCents($first->total_amount);
        $endInCents = MonthlyPortfolioSnapshot::toCents($last->total_amount);
        $growthInCents = $endInCents - $startInCents;

        return [
            'currency' => 'EUR',
            'from_month' => $first->month_date?->toDateString(),
            'to_month' => $last->month_date?->toDateString(),
            'months' => $window->count(),
            'start_total' => $first->total_amount,
            'end_total' => $last->total_amount,
            'growth_amount' => MonthlyPortfolioSnapshot::fromCents($growthInCents),
            'growth_percentage' => $startInCents === 0
                ? null
                : number_format($growthInCents / $startInCents * 100, 2, '.', ''),
            'points' => $points,
        ];
    }

    /**
     * Savings accounts with interest rates, computed interest, and weighted APY.
     *
     * @return array{
     *     currency: string,
     *     accounts: array<int, array{name: string, person: string|null, amount: string, apy: string, annual_interest: string, monthly_interest: string}>,
     *     totals: array{total_amount: string, weighted_apy: string, total_annual_interest: string, total_monthly_interest: string}
     * }
     */
    public function savingsAccounts(): array
    {
        $accounts = SavingsAccount::query()
            ->roots()
            ->with('person:id,name')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        $rows = [];
        $totalInCents = 0;
        $weightedSum = 0.0;
        $annualInterestInCents = 0;
        $monthlyInterestInCents = 0;

        foreach ($accounts as $account) {
            $amountInCents = SavingsAccount::toCents($account->amount);
            $apy = (float) $account->apy;
            $annual = (int) round($amountInCents * $apy / 100);
            $monthly = (int) round($amountInCents * $apy / 100 / 12);

            $rows[] = [
                'name' => $account->name,
                'person' => $account->person?->name,
                'amount' => $account->amount,
                'apy' => $account->apy,
                'annual_interest' => SavingsAccount::fromCents($annual),
                'monthly_interest' => SavingsAccount::fromCents($monthly),
            ];

            $totalInCents += $amountInCents;
            $weightedSum += $amountInCents * $apy;
            $annualInterestInCents += $annual;
            $monthlyInterestInCents += $monthly;
        }

        return [
            'currency' => 'EUR',
            'accounts' => $rows,
            'totals' => [
                'total_amount' => SavingsAccount::fromCents($totalInCents),
                'weighted_apy' => $totalInCents === 0
                    ? '0.00'
                    : number_format($weightedSum / $totalInCents, 2, '.', ''),
                'total_annual_interest' => SavingsAccount::fromCents($annualInterestInCents),
                'total_monthly_interest' => SavingsAccount::fromCents($monthlyInterestInCents),
            ],
        ];
    }

    /**
     * Investment holdings: securities (ETF/stock/bond) with cost basis and P/L,
     * plus crypto holdings aggregated by symbol, including configured wallet APY.
     *
     * @return array{
     *     currency: string,
     *     securities: array<int, array<string, mixed>>,
     *     crypto: array<int, array<string, mixed>>,
     *     totals: array{total_invested: string, current_value: string, profit_loss: string, profit_loss_after_tax: string, crypto_value: string}
     * }
     */
    public function investmentHoldings(): array
    {
        $purchases = InvestmentPurchase::query()
            ->with('symbol')
            ->whereHas(
                'symbol',
                fn ($query) => $query->where('type', '!=', InvestmentSymbolType::CRYPTO->value),
            )
            ->get();

        $investedInCents = 0;
        $currentInCents = 0;
        $profitLossInCents = 0;
        $profitLossAfterTaxInCents = 0;

        $securities = $purchases
            ->groupBy('investment_symbol_id')
            ->map(function (Collection $group) use (
                &$investedInCents,
                &$currentInCents,
                &$profitLossInCents,
                &$profitLossAfterTaxInCents,
            ): array {
                /** @var InvestmentPurchase $first */
                $first = $group->firstOrFail();
                $invested = 0;
                $current = 0;
                $profitLoss = 0;
                $profitLossAfterTax = 0;
                $tax = 0;
                $quantity = 0.0;

                foreach ($group as $purchase) {
                    $metrics = $this->investmentPortfolioService->calculateMetrics($purchase);

                    $invested += MonthlyPortfolioSnapshot::toCents($metrics['price']);
                    $current += MonthlyPortfolioSnapshot::toCents($metrics['current_value']);
                    $profitLoss += MonthlyPortfolioSnapshot::toCents($metrics['profit_loss']);
                    $profitLossAfterTax += MonthlyPortfolioSnapshot::toCents($metrics['profit_loss_after_tax']);
                    $tax += MonthlyPortfolioSnapshot::toCents($metrics['tax_liability']);
                    $quantity += $purchase->signedQuantity();
                }

                $investedInCents += $invested;
                $currentInCents += $current;
                $profitLossInCents += $profitLoss;
                $profitLossAfterTaxInCents += $profitLossAfterTax;

                return [
                    'symbol' => $first->symbol->symbol,
                    'type' => $first->symbol->type->value,
                    'type_label' => $first->symbol->type->label(),
                    'taxable' => $first->symbol->taxable,
                    'quantity' => number_format($quantity, 8, '.', ''),
                    'current_price' => $first->symbol->current_price,
                    'total_invested' => MonthlyPortfolioSnapshot::fromCents($invested),
                    'current_value' => MonthlyPortfolioSnapshot::fromCents($current),
                    'profit_loss' => MonthlyPortfolioSnapshot::fromCents($profitLoss),
                    'profit_loss_after_tax' => MonthlyPortfolioSnapshot::fromCents($profitLossAfterTax),
                    'tax_liability' => MonthlyPortfolioSnapshot::fromCents($tax),
                    'return_percentage' => $invested === 0
                        ? '0.00'
                        : number_format($profitLoss / $invested * 100, 2, '.', ''),
                ];
            })
            ->sortByDesc(fn (array $row): int => MonthlyPortfolioSnapshot::toCents($row['current_value']))
            ->values()
            ->all();

        $crypto = $this->cryptoPortfolioService->balanceSymbolSummary();
        $cryptoValueInCents = collect($crypto)
            ->sum(fn (array $row): int => MonthlyPortfolioSnapshot::toCents($row['current_value']));

        return [
            'currency' => 'EUR',
            'securities' => $securities,
            'crypto' => $crypto,
            'totals' => [
                'total_invested' => MonthlyPortfolioSnapshot::fromCents($investedInCents),
                'current_value' => MonthlyPortfolioSnapshot::fromCents($currentInCents),
                'profit_loss' => MonthlyPortfolioSnapshot::fromCents($profitLossInCents),
                'profit_loss_after_tax' => MonthlyPortfolioSnapshot::fromCents($profitLossAfterTaxInCents),
                'crypto_value' => MonthlyPortfolioSnapshot::fromCents($cryptoValueInCents),
            ],
        ];
    }

    /**
     * Household income (net, gross, contributions, taxes, bonuses) aggregated by year,
     * with year-over-year change for the latest year.
     *
     * @return array{
     *     currency: string,
     *     active_people: array<int, string>,
     *     by_year: array<int, array<string, mixed>>,
     *     latest_year_change: array{from_year: int, to_year: int, net_change_amount: string, net_change_percentage: string|null}|null
     * }
     */
    public function incomeSummary(): array
    {
        $activePeople = Person::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name']);

        $paycheckYears = PaycheckYear::query()
            ->with([
                'paychecks:id,paycheck_year_id,month,net,gross,contributions,taxes',
                'bonuses:id,paycheck_year_id,amount,taxable,paid_tax',
            ])
            ->whereIn('person_id', $activePeople->pluck('id'))
            ->orderBy('year')
            ->get();

        /** @var array<int, array<string, int|array<int, int>>> $byYear */
        $byYear = [];

        foreach ($paycheckYears as $paycheckYear) {
            $year = $paycheckYear->year;
            $byYear[$year] ??= [
                'net' => 0,
                'gross' => 0,
                'contributions' => 0,
                'taxes' => 0,
                'bonuses_gross' => 0,
                'bonuses_net' => 0,
                'people' => [],
                'months' => [],
            ];

            foreach ($paycheckYear->paychecks as $paycheck) {
                $byYear[$year]['net'] += MonthlyPortfolioSnapshot::toCents($paycheck->net);
                $byYear[$year]['gross'] += MonthlyPortfolioSnapshot::toCents($paycheck->gross);
                $byYear[$year]['contributions'] += MonthlyPortfolioSnapshot::toCents($paycheck->contributions);
                $byYear[$year]['taxes'] += MonthlyPortfolioSnapshot::toCents($paycheck->taxes);
                $byYear[$year]['months'][$paycheck->month] = true;
            }

            foreach ($paycheckYear->bonuses as $bonus) {
                $byYear[$year]['bonuses_gross'] += MonthlyPortfolioSnapshot::toCents($bonus->amount);
                $byYear[$year]['bonuses_net'] += $this->bonusNetInCents($bonus);
            }

            $byYear[$year]['people'][$paycheckYear->person_id] = true;
        }

        ksort($byYear);

        $rows = [];

        foreach ($byYear as $year => $data) {
            $netWithBonusesInCents = $data['net'] + $data['bonuses_net'];

            $rows[] = [
                'year' => $year,
                'net' => MonthlyPortfolioSnapshot::fromCents($data['net']),
                'gross' => MonthlyPortfolioSnapshot::fromCents($data['gross']),
                'contributions' => MonthlyPortfolioSnapshot::fromCents($data['contributions']),
                'taxes' => MonthlyPortfolioSnapshot::fromCents($data['taxes']),
                'bonuses_gross' => MonthlyPortfolioSnapshot::fromCents($data['bonuses_gross']),
                'bonuses_net' => MonthlyPortfolioSnapshot::fromCents($data['bonuses_net']),
                'net_with_bonuses' => MonthlyPortfolioSnapshot::fromCents($netWithBonusesInCents),
                'people_count' => count($data['people']),
                'months_recorded' => count($data['months']),
            ];
        }

        return [
            'currency' => 'EUR',
            'active_people' => $activePeople->pluck('name')->all(),
            'by_year' => $rows,
            'latest_year_change' => $this->latestYearChange($rows),
        ];
    }

    /**
     * Slovenian income-tax analysis (dohodnina) per active person for their latest
     * recorded year, including reliefs, the annual settlement difference, and projection.
     *
     * @return array{
     *     currency: string,
     *     people: array<int, array<string, mixed>>
     * }
     */
    public function taxAnalysis(): array
    {
        $activePeople = Person::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name']);

        $people = [];

        foreach ($activePeople as $person) {
            $latest = PaycheckYear::query()
                ->where('person_id', $person->id)
                ->orderByDesc('year')
                ->first();

            if (! $latest instanceof PaycheckYear) {
                continue;
            }

            $calculation = $this->taxCalculationService->calculate($latest);

            $people[] = [
                'person' => $person->name,
                'year' => $latest->year,
                'has_tax_settings' => $calculation['has_tax_settings'],
                'sum_gross' => $calculation['sum_gross'],
                'sum_net' => $calculation['sum_net'],
                'sum_contributions' => $calculation['sum_contributions'],
                'sum_taxes' => $calculation['sum_taxes'],
                'tax_base' => $calculation['davcna_osnova'],
                'reliefs' => $calculation['olajsave'],
                'reliefs_breakdown' => $calculation['breakdown'],
                'dohodnina' => $calculation['dohodnina'],
                'settlement_difference' => $calculation['razlika'],
                'projection' => $calculation['projection'],
            ];
        }

        return [
            'currency' => 'EUR',
            'people' => $people,
        ];
    }

    /**
     * Upcoming bond coupon and maturity events, plus total bond exposure.
     *
     * @return array{
     *     currency: string,
     *     today: string,
     *     events: array<int, array<string, mixed>>
     * }
     */
    public function bondSchedule(): array
    {
        $today = CarbonImmutable::now('Europe/Ljubljana')->startOfDay();

        $bonds = InvestmentPurchase::query()
            ->with(['symbol:id,symbol,type', 'provider:id,name'])
            ->whereHas(
                'symbol',
                fn ($query) => $query->where('type', InvestmentSymbolType::BOND->value),
            )
            ->get();

        $events = [];

        foreach ($bonds as $bond) {
            foreach (['coupon' => $bond->coupon_date, 'expiry' => $bond->expiry_date] as $type => $date) {
                if ($date === null) {
                    continue;
                }

                $eventDate = CarbonImmutable::parse($date)->startOfDay();

                $events[] = [
                    'type' => $type,
                    'symbol' => $bond->symbol->symbol,
                    'provider' => $bond->provider->name,
                    'date' => $eventDate->toDateString(),
                    'days_until' => (int) $today->diffInDays($eventDate, false),
                    'is_upcoming' => $eventDate->greaterThanOrEqualTo($today),
                    'quantity' => $bond->quantity,
                    'yield' => $bond->yield,
                ];
            }
        }

        usort($events, fn (array $a, array $b): int => strcmp($a['date'], $b['date']));

        return [
            'currency' => 'EUR',
            'today' => $today->toDateString(),
            'events' => $events,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array{from_year: int, to_year: int, net_change_amount: string, net_change_percentage: string|null}|null
     */
    private function latestYearChange(array $rows): ?array
    {
        if (count($rows) < 2) {
            return null;
        }

        $latest = $rows[array_key_last($rows)];
        $previous = $rows[array_key_last($rows) - 1];
        $latestNetInCents = MonthlyPortfolioSnapshot::toCents($latest['net']);
        $previousNetInCents = MonthlyPortfolioSnapshot::toCents($previous['net']);
        $changeInCents = $latestNetInCents - $previousNetInCents;

        return [
            'from_year' => $previous['year'],
            'to_year' => $latest['year'],
            'net_change_amount' => MonthlyPortfolioSnapshot::fromCents($changeInCents),
            'net_change_percentage' => $previousNetInCents === 0
                ? null
                : number_format($changeInCents / $previousNetInCents * 100, 2, '.', ''),
        ];
    }

    private function bonusNetInCents(Bonus $bonus): int
    {
        $amountInCents = MonthlyPortfolioSnapshot::toCents($bonus->amount);

        if (! $bonus->taxable) {
            return $amountInCents;
        }

        return $amountInCents - MonthlyPortfolioSnapshot::toCents($bonus->paid_tax);
    }

    /**
     * @param  array<string, string>  $amounts
     */
    private function sumCents(array $amounts): int
    {
        return collect($amounts)
            ->sum(fn (string $amount): int => MonthlyPortfolioSnapshot::toCents($amount));
    }
}
