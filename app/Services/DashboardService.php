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
    /**
     * @var array<int, array<string, mixed>>|null
     */
    private ?array $memoizedSnapshotRows = null;

    public function __construct(
        private readonly InvestmentPortfolioService $investmentPortfolioService,
        private readonly MonthlyPortfolioSnapshotService $monthlyPortfolioSnapshotService,
        private readonly PortfolioGrowthAttributionService $growthAttribution,
    ) {}

    /**
     * @return array{
     *     netWorth: array<string, mixed>,
     *     snapshotChange: array<string, mixed>,
     *     allocation: array<int, array<string, mixed>>,
     *     income: array<string, mixed>,
     *     longView: array<string, mixed>
     * }
     */
    public function pageData(): array
    {
        $activePeopleCount = Person::where('is_active', true)->count();
        $currentStateTotals = $this->monthlyPortfolioSnapshotService->currentStateTotals();
        $currentTotalInCents = $this->sumAmountsInCents($currentStateTotals);
        $snapshotRows = $this->snapshotRows();

        return [
            'netWorth' => [
                'current_total' => $this->fromCents($currentTotalInCents),
                'as_of_label' => now('Europe/Ljubljana')
                    ->locale('sl')
                    ->translatedFormat('j. F Y'),
            ],
            'snapshotChange' => $this->snapshotChange(
                $currentStateTotals,
                $currentTotalInCents,
                $snapshotRows,
            ),
            'allocation' => $this->allocationItems($currentStateTotals, $currentTotalInCents, $snapshotRows),
            'income' => [
                'latest_full_month' => $this->latestFullIncomeMonth($activePeopleCount),
                'current_month' => $this->currentMonthIncome($activePeopleCount),
                'monthly_interest' => $this->monthlySavingsInterest(),
            ],
            'longView' => $this->longView($snapshotRows),
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
        $rows = $this->snapshotRows();

        return [
            'latest_snapshot' => $rows === [] ? null : $rows[array_key_last($rows)],
            'points' => collect($rows)
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
     * Live category totals, ordered by value, each carrying the change the
     * latest snapshot recorded against the one before it.
     *
     * @param  array<string, string>  $totals
     * @param  array<int, array<string, mixed>>  $snapshotRows
     * @return array<int, array<string, mixed>>
     */
    private function allocationItems(array $totals, int $currentTotalInCents, array $snapshotRows): array
    {
        $meta = [
            'savings_amount' => ['label' => 'Varčevanje', 'color' => '#2563eb'],
            'bond_amount' => ['label' => 'Obveznice', 'color' => '#f97316'],
            'etf_amount' => ['label' => 'ETF', 'color' => '#ef4444'],
            'stock_amount' => ['label' => 'Delnice', 'color' => '#0f766e'],
            'crypto_amount' => ['label' => 'Kripto', 'color' => '#f59e0b'],
        ];
        $monthDiffsInCents = $this->categoryMonthDiffsInCents($snapshotRows);

        return collect($meta)
            ->map(function (array $item, string $key) use ($totals, $currentTotalInCents, $monthDiffsInCents): array {
                $amountInCents = $this->toCents($totals[$key] ?? 0);

                return [
                    'key' => $key,
                    'label' => $item['label'],
                    'amount' => $this->fromCents($amountInCents),
                    'amount_in_cents' => $amountInCents,
                    'share_percentage' => $currentTotalInCents === 0
                        ? 0
                        : round(($amountInCents / $currentTotalInCents) * 100, 2),
                    'month_diff_amount' => array_key_exists($key, $monthDiffsInCents)
                        ? $this->fromCents($monthDiffsInCents[$key])
                        : null,
                    'color' => $item['color'],
                ];
            })
            ->sortByDesc('amount_in_cents')
            ->map(function (array $item): array {
                unset($item['amount_in_cents']);

                return $item;
            })
            ->values()
            ->all();
    }

    /**
     * Month-over-month change per category, keyed by snapshot column.
     *
     * @param  array<int, array<string, mixed>>  $snapshotRows
     * @return array<string, int>
     */
    private function categoryMonthDiffsInCents(array $snapshotRows): array
    {
        if (count($snapshotRows) < 2) {
            return [];
        }

        $latest = $snapshotRows[array_key_last($snapshotRows)];
        $previous = $snapshotRows[count($snapshotRows) - 2];
        $diffs = [];

        foreach (['savings_amount', 'bond_amount', 'etf_amount', 'stock_amount', 'crypto_amount'] as $key) {
            $diffs[$key] = $this->toCents($latest[$key]) - $this->toCents($previous[$key]);
        }

        return $diffs;
    }

    /**
     * The headline change: how the live state has drifted since the last
     * recorded snapshot, split into savings inflow, money paid into
     * investments and market movement. The three segments add up to the total.
     *
     * @param  array<string, string>  $currentStateTotals
     * @param  array<int, array<string, mixed>>  $snapshotRows
     * @return array<string, mixed>
     */
    private function snapshotChange(
        array $currentStateTotals,
        int $currentTotalInCents,
        array $snapshotRows,
    ): array {
        if ($snapshotRows === []) {
            return [
                'available' => false,
                'snapshot_month_label' => null,
                'snapshot_total' => null,
                'current_total' => $this->fromCents($currentTotalInCents),
                'diff_amount' => null,
                'diff_percentage' => null,
                'segments' => [],
            ];
        }

        $latest = $snapshotRows[array_key_last($snapshotRows)];
        $snapshotTotalInCents = $this->toCents($latest['total_amount']);
        $diffInCents = $currentTotalInCents - $snapshotTotalInCents;

        return [
            'available' => true,
            'snapshot_month_label' => $this->monthLabelFromDate($latest['month_date']),
            'snapshot_total' => $this->fromCents($snapshotTotalInCents),
            'current_total' => $this->fromCents($currentTotalInCents),
            'diff_amount' => $this->fromCents($diffInCents),
            'diff_percentage' => $snapshotTotalInCents === 0
                ? null
                : number_format(($diffInCents / $snapshotTotalInCents) * 100, 2, '.', ''),
            'segments' => $this->withSegmentShares(
                $this->snapshotChangeSegments($currentStateTotals, $latest),
            ),
        ];
    }

    /**
     * Contributions are read straight off the transaction ledger for the window
     * that starts at the snapshot, so the window is exact rather than rounded
     * to whole months the way the snapshot-to-snapshot breakdown is.
     *
     * @param  array<string, string>  $currentStateTotals
     * @param  array<string, mixed>  $latestSnapshot
     * @return array<int, array<string, mixed>>
     */
    private function snapshotChangeSegments(array $currentStateTotals, array $latestSnapshot): array
    {
        $savingsDiffInCents = $this->toCents($currentStateTotals['savings_amount'] ?? 0)
            - $this->toCents($latestSnapshot['savings_amount']);

        $flows = $this->growthAttribution->flowsBetween(
            CarbonImmutable::parse($latestSnapshot['month_date'], 'Europe/Ljubljana'),
            CarbonImmutable::now('Europe/Ljubljana'),
        );

        $investmentsDiffInCents = 0;
        $contributionInCents = 0;

        foreach ($this->growthAttribution->bucketKeys() as $key) {
            $investmentsDiffInCents += $this->toCents($currentStateTotals[$key] ?? 0)
                - $this->toCents($latestSnapshot[$key]);
            $contributionInCents += $flows[$key]['contribution'] ?? 0;
        }

        return [
            [
                'key' => 'savings',
                'label' => 'Prilivi in obresti',
                'amount' => $this->fromCents($savingsDiffInCents),
                'color' => '#0f766e',
            ],
            [
                'key' => 'contribution',
                'label' => 'Vloženo v naložbe',
                'amount' => $this->fromCents($contributionInCents),
                'color' => '#10b981',
            ],
            [
                'key' => 'market',
                'label' => 'Tržna sprememba',
                'amount' => $this->fromCents($investmentsDiffInCents - $contributionInCents),
                'color' => '#6ee7b7',
            ],
        ];
    }

    /**
     * Shares are taken over absolute amounts so a negative segment still gets a
     * visible width and the bar always adds up to 100 %.
     *
     * @param  array<int, array<string, mixed>>  $segments
     * @return array<int, array<string, mixed>>
     */
    private function withSegmentShares(array $segments): array
    {
        $absoluteTotalInCents = collect($segments)
            ->sum(fn (array $segment): int => abs($this->toCents($segment['amount'])));

        return collect($segments)
            ->map(function (array $segment) use ($absoluteTotalInCents): array {
                $segment['share_percentage'] = $absoluteTotalInCents === 0
                    ? 0
                    : round((abs($this->toCents($segment['amount'])) / $absoluteTotalInCents) * 100, 2);

                return $segment;
            })
            ->all();
    }

    /**
     * Trend context the chart only hints at: growth over the last year, the
     * average month inside it, and how long the streak of growing months is.
     *
     * @param  array<int, array<string, mixed>>  $snapshotRows
     * @return array<string, mixed>
     */
    private function longView(array $snapshotRows): array
    {
        $rowCount = count($snapshotRows);

        if ($rowCount < 2) {
            return [
                'available' => false,
                'months' => 0,
                'growth_amount' => null,
                'growth_percentage' => null,
                'average_monthly_growth' => null,
                'consecutive_growth_months' => 0,
            ];
        }

        $months = min(12, $rowCount - 1);
        $latestTotalInCents = $this->toCents($snapshotRows[array_key_last($snapshotRows)]['total_amount']);
        $baseTotalInCents = $this->toCents($snapshotRows[$rowCount - 1 - $months]['total_amount']);
        $growthInCents = $latestTotalInCents - $baseTotalInCents;

        return [
            'available' => true,
            'months' => $months,
            'growth_amount' => $this->fromCents($growthInCents),
            'growth_percentage' => $baseTotalInCents === 0
                ? null
                : number_format(($growthInCents / $baseTotalInCents) * 100, 2, '.', ''),
            'average_monthly_growth' => $this->fromCents((int) round($growthInCents / $months)),
            'consecutive_growth_months' => $this->consecutiveGrowthMonths($snapshotRows),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $snapshotRows
     */
    private function consecutiveGrowthMonths(array $snapshotRows): int
    {
        $streak = 0;

        foreach (array_reverse($snapshotRows) as $row) {
            if ($row['diff_amount'] === null || $this->toCents($row['diff_amount']) <= 0) {
                break;
            }

            $streak++;
        }

        return $streak;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function snapshotRows(): array
    {
        return $this->memoizedSnapshotRows ??= $this->monthlyPortfolioSnapshotService->pageData()['rows'];
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

    private function monthLabel(int $year, int $month): string
    {
        return CarbonImmutable::create($year, $month, 1, 0, 0, 0, 'Europe/Ljubljana')
            ->locale('sl')
            ->translatedFormat('F Y');
    }

    private function monthLabelFromDate(?string $monthDate): ?string
    {
        if ($monthDate === null) {
            return null;
        }

        $date = CarbonImmutable::parse($monthDate, 'Europe/Ljubljana');

        return $this->monthLabel((int) $date->year, (int) $date->month);
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
