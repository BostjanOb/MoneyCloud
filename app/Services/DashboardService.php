<?php

namespace App\Services;

use App\Models\InvestmentPurchase;
use App\Models\MonthlyPortfolioSnapshot;
use App\Models\Paycheck;
use App\Models\Person;
use App\Models\SavingsAccount;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class DashboardService
{
    public function __construct(
        private readonly InvestmentPortfolioService $investmentPortfolioService,
        private readonly MonthlyPortfolioSnapshotService $monthlyPortfolioSnapshotService,
    ) {}

    /**
     * @return array{
     *     hero: array<string, mixed>,
     *     allocation: array<string, mixed>,
     *     income: array<string, mixed>,
     *     alerts: array<int, array<string, mixed>>,
     *     quickActions: array<int, array<string, mixed>>
     * }
     */
    public function pageData(): array
    {
        $activePeople = Person::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->orderBy('id')
            ->get(['id', 'slug', 'name']);
        $activePeopleCount = $activePeople->count();
        $currentStateTotals = $this->monthlyPortfolioSnapshotService->currentStateTotals();
        $currentTotalInCents = $this->sumAmountsInCents($currentStateTotals);
        $snapshotComparison = $this->snapshotComparison($currentTotalInCents);
        $latestFullMonth = $this->latestFullIncomeMonth($activePeopleCount);
        $currentMonthIncome = $this->currentMonthIncome($activePeopleCount);
        $monthlyInterest = $this->monthlySavingsInterest();

        return [
            'hero' => [
                'current_total' => [
                    'title' => 'Trenutno skupaj',
                    'value' => $this->fromCents($currentTotalInCents),
                    'subtitle' => 'Živo stanje vseh kategorij premoženja.',
                    'tone' => 'neutral',
                ],
                'snapshot_change' => [
                    'title' => 'Sprememba od zadnjega posnetka',
                    'value' => $snapshotComparison['value'],
                    'percentage' => $snapshotComparison['percentage'],
                    'subtitle' => $snapshotComparison['subtitle'],
                    'tone' => $snapshotComparison['tone'],
                ],
                'latest_income' => [
                    'title' => 'Neto prejemki zadnjega polnega meseca',
                    'value' => $latestFullMonth['total_net'] ?? null,
                    'subtitle' => $latestFullMonth === null
                        ? 'Ni še popolnega meseca z vsemi aktivnimi osebami.'
                        : sprintf('Mesec: %s', $latestFullMonth['month_label']),
                    'tone' => $latestFullMonth === null ? 'warning' : 'neutral',
                ],
                'monthly_interest' => [
                    'title' => 'Mesečne obresti',
                    'value' => $monthlyInterest,
                    'subtitle' => 'Ocena pri trenutnih obrestnih merah.',
                    'tone' => 'positive',
                ],
            ],
            'allocation' => [
                'total_amount' => $this->fromCents($currentTotalInCents),
                'items' => $this->allocationItems($currentStateTotals, $currentTotalInCents),
            ],
            'income' => [
                'latest_full_month' => $latestFullMonth,
                'current_month' => $currentMonthIncome,
            ],
            'alerts' => $this->alerts($currentMonthIncome, $activePeopleCount, $activePeople),
            'quickActions' => $this->quickActions($activePeople),
        ];
    }

    /**
     * @return array{
     *     latest_snapshot: array<string, mixed>|null,
     *     points: array<int, array<string, mixed>>
     * }
     */
    public function trendData(): array
    {
        $pageData = $this->monthlyPortfolioSnapshotService->pageData();

        return [
            'latest_snapshot' => $pageData['latest'],
            'points' => collect($pageData['rows'])
                ->map(fn (array $row): array => [
                    'month_date' => $row['month_date'],
                    'month_label' => $row['month_label'],
                    'total_amount' => $row['total_amount'],
                    'diff_amount' => $row['diff_amount'],
                ])
                ->all(),
        ];
    }

    /**
     * @return array{
     *     summary: array<string, mixed>,
     *     top_positions: array<int, array<string, mixed>>
     * }
     */
    public function investmentData(): array
    {
        $purchases = InvestmentPurchase::query()
            ->with('symbol')
            ->orderBy('purchased_at')
            ->orderBy('id')
            ->get();

        if ($purchases->isEmpty()) {
            return [
                'summary' => [
                    'total_invested' => '0.00',
                    'current_value' => '0.00',
                    'profit_loss' => '0.00',
                    'profit_loss_after_tax' => '0.00',
                    'purchase_count' => 0,
                ],
                'top_positions' => [],
            ];
        }

        $summary = [
            'total_invested' => 0,
            'current_value' => 0,
            'profit_loss' => 0,
            'profit_loss_after_tax' => 0,
            'purchase_count' => $purchases->count(),
        ];

        $topPositions = $purchases
            ->groupBy('investment_symbol_id')
            ->map(function (Collection $symbolPurchases): array {
                /** @var InvestmentPurchase $firstPurchase */
                $firstPurchase = $symbolPurchases->firstOrFail();
                $position = [
                    'symbol' => $firstPurchase->symbol->symbol,
                    'type_label' => $firstPurchase->symbol->type->label(),
                    'quantity' => 0.0,
                    'total_invested' => 0,
                    'current_value' => 0,
                    'profit_loss' => 0,
                    'profit_loss_after_tax' => 0,
                ];

                foreach ($symbolPurchases as $purchase) {
                    $metrics = $this->investmentPortfolioService->calculateMetrics($purchase);

                    $position['quantity'] += $purchase->signedQuantity();
                    $position['total_invested'] += $this->toCents($metrics['price']);
                    $position['current_value'] += $this->toCents($metrics['current_value']);
                    $position['profit_loss'] += $this->toCents($metrics['profit_loss']);
                    $position['profit_loss_after_tax'] += $this->toCents($metrics['profit_loss_after_tax']);
                }

                return [
                    'symbol' => $position['symbol'],
                    'type_label' => $position['type_label'],
                    'quantity' => number_format($position['quantity'], 8, '.', ''),
                    'total_invested' => $this->fromCents($position['total_invested']),
                    'current_value' => $this->fromCents($position['current_value']),
                    'profit_loss' => $this->fromCents($position['profit_loss']),
                    'profit_loss_after_tax' => $this->fromCents($position['profit_loss_after_tax']),
                ];
            })
            ->sortByDesc(fn (array $position): int => $this->toCents($position['current_value']))
            ->take(5)
            ->values();

        foreach ($purchases as $purchase) {
            $metrics = $this->investmentPortfolioService->calculateMetrics($purchase);

            $summary['total_invested'] += $this->toCents($metrics['price']);
            $summary['current_value'] += $this->toCents($metrics['current_value']);
            $summary['profit_loss'] += $this->toCents($metrics['profit_loss']);
            $summary['profit_loss_after_tax'] += $this->toCents($metrics['profit_loss_after_tax']);
        }

        return [
            'summary' => [
                'total_invested' => $this->fromCents($summary['total_invested']),
                'current_value' => $this->fromCents($summary['current_value']),
                'profit_loss' => $this->fromCents($summary['profit_loss']),
                'profit_loss_after_tax' => $this->fromCents($summary['profit_loss_after_tax']),
                'purchase_count' => $summary['purchase_count'],
            ],
            'top_positions' => $topPositions->all(),
        ];
    }

    /**
     * @param  array<string, string>  $totals
     * @return array<int, array<string, mixed>>
     */
    private function allocationItems(array $totals, int $currentTotalInCents): array
    {
        $meta = [
            'savings_amount' => ['label' => 'Varčevanje', 'color' => '#2563eb'],
            'bond_amount' => ['label' => 'Obveznice', 'color' => '#f97316'],
            'etf_amount' => ['label' => 'ETF', 'color' => '#ef4444'],
            'stock_amount' => ['label' => 'Delnice', 'color' => '#0f766e'],
            'crypto_amount' => ['label' => 'Kripto', 'color' => '#f59e0b'],
        ];

        return collect($meta)
            ->map(function (array $item, string $key) use ($totals, $currentTotalInCents): array {
                $amountInCents = $this->toCents($totals[$key] ?? 0);

                return [
                    'key' => $key,
                    'label' => $item['label'],
                    'amount' => $this->fromCents($amountInCents),
                    'share_percentage' => $currentTotalInCents === 0
                        ? 0
                        : round(($amountInCents / $currentTotalInCents) * 100, 2),
                    'color' => $item['color'],
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, Person>  $activePeople
     * @param  array<string, mixed>  $currentMonthIncome
     * @return array<int, array<string, mixed>>
     */
    private function alerts(
        array $currentMonthIncome,
        int $activePeopleCount,
        Collection $activePeople,
    ): array {
        $alerts = [];
        $currentMonth = now('Europe/Ljubljana')->startOfMonth();
        $paychecksHref = $this->paychecksHref($activePeople);

        if (! MonthlyPortfolioSnapshot::query()->whereDate('month_date', $currentMonth)->exists()) {
            $alerts[] = [
                'key' => 'missing_snapshot',
                'title' => sprintf('Manjka mesečni posnetek za %s', $this->monthLabel(
                    (int) $currentMonth->year,
                    (int) $currentMonth->month,
                )),
                'message' => 'Dashboard primerjavo uporablja zadnji obstoječi posnetek, zato je smiselno dodati novega.',
                'href' => route('statistics.monthly-summary'),
                'action_label' => 'Odpri mesečni povzetek',
            ];
        }

        if (
            $activePeopleCount > 0
            && ! $currentMonthIncome['is_complete']
            && $currentMonthIncome['entered_people_count'] < $currentMonthIncome['expected_people_count']
        ) {
            $alerts[] = [
                'key' => 'incomplete_current_month_income',
                'title' => sprintf('Plače za %s še niso popolne', $currentMonthIncome['month_label']),
                'message' => sprintf(
                    'Vnesenih je %d od %d aktivnih oseb.',
                    $currentMonthIncome['entered_people_count'],
                    $currentMonthIncome['expected_people_count'],
                ),
                'href' => $paychecksHref,
                'action_label' => 'Odpri plače',
            ];
        }

        return $alerts;
    }

    /**
     * @param  Collection<int, Person>  $activePeople
     * @return array<int, array<string, string>>
     */
    private function quickActions(Collection $activePeople): array
    {
        return [
            [
                'label' => 'Dodaj mesečni posnetek',
                'href' => route('statistics.monthly-summary'),
                'variant' => 'default',
            ],
            [
                'label' => 'Odpri plače',
                'href' => $this->paychecksHref($activePeople),
                'variant' => 'outline',
            ],
            [
                'label' => 'Odpri varčevanje',
                'href' => route('savings.index'),
                'variant' => 'outline',
            ],
            [
                'label' => 'Odpri investicije',
                'href' => route('investments.providers.index'),
                'variant' => 'outline',
            ],
            [
                'label' => 'Odpri kripto',
                'href' => route('crypto.balances.index'),
                'variant' => 'outline',
            ],
        ];
    }

    /**
     * @return array{
     *     title: string,
     *     value: string|null,
     *     percentage: string|null,
     *     subtitle: string,
     *     tone: string
     * }
     */
    private function snapshotComparison(int $currentTotalInCents): array
    {
        $latestSnapshot = MonthlyPortfolioSnapshot::query()
            ->orderByDesc('month_date')
            ->orderByDesc('id')
            ->first();

        if (! $latestSnapshot instanceof MonthlyPortfolioSnapshot) {
            return [
                'title' => 'Sprememba od zadnjega posnetka',
                'value' => null,
                'percentage' => null,
                'subtitle' => 'Mesečni posnetek še ni dodan.',
                'tone' => 'warning',
            ];
        }

        $snapshotTotalInCents = MonthlyPortfolioSnapshot::toCents($latestSnapshot->total_amount);
        $diffInCents = $currentTotalInCents - $snapshotTotalInCents;
        $diffPercentage = $snapshotTotalInCents === 0
            ? null
            : number_format(($diffInCents / $snapshotTotalInCents) * 100, 2, '.', '');

        return [
            'title' => 'Sprememba od zadnjega posnetka',
            'value' => $this->fromCents($diffInCents),
            'percentage' => $diffPercentage,
            'subtitle' => sprintf(
                'Primerjava s posnetkom za %s.',
                $this->monthLabel((int) $latestSnapshot->month_date?->year, (int) $latestSnapshot->month_date?->month),
            ),
            'tone' => $diffInCents > 0 ? 'positive' : ($diffInCents < 0 ? 'negative' : 'neutral'),
        ];
    }

    /**
     * @return array{
     *     month_key: string,
     *     month_label: string,
     *     total_net: string,
     *     entered_people_count: int,
     *     expected_people_count: int
     * }|null
     */
    private function latestFullIncomeMonth(int $activePeopleCount): ?array
    {
        if ($activePeopleCount === 0) {
            return null;
        }

        /** @var array<string, mixed>|null $row */
        $row = $this->monthlyIncomeRows()
            ->first(fn (array $row): bool => $row['entered_people_count'] === $activePeopleCount);

        return $row;
    }

    /**
     * @return array{
     *     month_key: string,
     *     month_label: string,
     *     total_net: string,
     *     entered_people_count: int,
     *     expected_people_count: int,
     *     is_complete: bool
     * }
     */
    private function currentMonthIncome(int $activePeopleCount): array
    {
        $currentMonth = now('Europe/Ljubljana');
        $currentMonthKey = $currentMonth->format('Y-m');
        $currentMonthRow = $this->monthlyIncomeRows()
            ->first(fn (array $row): bool => $row['month_key'] === $currentMonthKey);

        return [
            'month_key' => $currentMonthKey,
            'month_label' => $this->monthLabel((int) $currentMonth->year, (int) $currentMonth->month),
            'total_net' => $currentMonthRow['total_net'] ?? '0.00',
            'entered_people_count' => $currentMonthRow['entered_people_count'] ?? 0,
            'expected_people_count' => $activePeopleCount,
            'is_complete' => $activePeopleCount === 0
                || (($currentMonthRow['entered_people_count'] ?? 0) === $activePeopleCount),
        ];
    }

    /**
     * @return Collection<int, array{
     *     month_key: string,
     *     month_label: string,
     *     total_net: string,
     *     entered_people_count: int,
     *     expected_people_count: int
     * }>
     */
    private function monthlyIncomeRows(): Collection
    {
        $activePeopleCount = Person::query()->where('is_active', true)->count();

        return Paycheck::query()
            ->selectRaw('paycheck_years.year AS year')
            ->selectRaw('paychecks.month AS month')
            ->selectRaw('SUM(paychecks.net) AS total_net')
            ->selectRaw('COUNT(DISTINCT paycheck_years.person_id) AS entered_people_count')
            ->join('paycheck_years', 'paycheck_years.id', '=', 'paychecks.paycheck_year_id')
            ->join('people', 'people.id', '=', 'paycheck_years.person_id')
            ->where('people.is_active', true)
            ->groupBy('paycheck_years.year', 'paychecks.month')
            ->orderByDesc('paycheck_years.year')
            ->orderByDesc('paychecks.month')
            ->get()
            ->map(fn (object $row): array => [
                'month_key' => sprintf('%04d-%02d', (int) $row->year, (int) $row->month),
                'month_label' => $this->monthLabel((int) $row->year, (int) $row->month),
                'total_net' => number_format((float) $row->total_net, 2, '.', ''),
                'entered_people_count' => (int) $row->entered_people_count,
                'expected_people_count' => $activePeopleCount,
            ]);
    }

    private function monthlySavingsInterest(): string
    {
        $amountInCents = SavingsAccount::query()
            ->roots()
            ->get(['id', 'amount', 'apy'])
            ->sum(fn (SavingsAccount $account): int => (int) round(
                SavingsAccount::toCents($account->amount) * ((float) $account->apy / 100) / 12,
            ));

        return $this->fromCents($amountInCents);
    }

    /**
     * @param  Collection<int, Person>  $activePeople
     */
    private function paychecksHref(Collection $activePeople): string
    {
        /** @var Person|null $primaryPerson */
        $primaryPerson = $activePeople->first();

        return $primaryPerson instanceof Person
            ? route('place.index', ['person' => $primaryPerson->slug])
            : route('people.index');
    }

    private function monthLabel(int $year, int $month): string
    {
        return CarbonImmutable::create($year, $month, 1, 0, 0, 0, 'Europe/Ljubljana')
            ->locale('sl')
            ->translatedFormat('F Y');
    }

    /**
     * @param  array<string, string>  $totals
     */
    private function sumAmountsInCents(array $totals): int
    {
        return collect($totals)
            ->sum(fn (string $value): int => $this->toCents($value));
    }

    private function fromCents(int $amountInCents): string
    {
        return MonthlyPortfolioSnapshot::fromCents($amountInCents);
    }

    private function toCents(string|int|float|null $amount): int
    {
        return MonthlyPortfolioSnapshot::toCents($amount);
    }
}
