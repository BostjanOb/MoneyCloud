<?php

namespace App\Services;

use App\Enums\InvestmentSymbolType;
use App\Models\CryptoBalance;
use App\Models\InvestmentPurchase;
use App\Models\MonthlyPortfolioSnapshot;
use App\Models\SavingsAccount;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class MonthlyPortfolioSnapshotService
{
    /**
     * Slovenian bucket labels, shared by the summary cards, the chart series
     * and the growth breakdown so they stay in sync.
     *
     * @var array<string, string>
     */
    private const BUCKET_LABELS = [
        'savings_amount' => 'Varčevanje',
        'bond_amount' => 'Obveznice',
        'etf_amount' => 'ETF',
        'crypto_amount' => 'Kripto',
        'stock_amount' => 'Delnice',
        'total_amount' => 'Skupaj',
    ];

    public function __construct(
        private PortfolioGrowthAttributionService $growthAttribution,
    ) {}

    /**
     * @return array{
     *     rows: array<int, array<string, mixed>>,
     *     chartSeries: array<int, array<string, mixed>>,
     *     summary_cards: array<int, array<string, mixed>>,
     *     latest: array<string, mixed>|null
     * }
     */
    public function pageData(): array
    {
        $rows = [];
        $previousSnapshot = null;
        $snapshots = MonthlyPortfolioSnapshot::ordered()->get();
        $flowsByMonth = $this->growthAttribution->flowsGroupedByMonth();

        foreach ($snapshots as $snapshot) {
            $rows[] = $this->transformSnapshot($snapshot, $previousSnapshot, $flowsByMonth);
            $previousSnapshot = $snapshot;
        }

        /** @var MonthlyPortfolioSnapshot|null $latestSnapshot */
        $latestSnapshot = $snapshots->last();
        $currentStateTotals = $this->currentStateTotals();

        return [
            'rows' => $rows,
            'chartSeries' => $this->buildChartSeries($rows),
            'summary_cards' => $this->buildSummaryCards($currentStateTotals, $latestSnapshot),
            'latest' => $rows === [] ? null : $rows[array_key_last($rows)],
        ];
    }

    /** @return array<string, string> */
    public function currentStateTotals(): array
    {
        $totals = [
            'savings_amount' => 0,
            'bond_amount' => 0,
            'etf_amount' => 0,
            'crypto_amount' => 0,
            'stock_amount' => 0,
        ];

        $totals['savings_amount'] = SavingsAccount::whereNull('parent_id')
            ->get(['id', 'amount'])
            ->sum(fn (SavingsAccount $account): int => SavingsAccount::toCents($account->amount));

        InvestmentPurchase::with('symbol:id,type,current_price')
            ->whereHas(
                'symbol',
                fn ($query) => $query->where('type', '!=', InvestmentSymbolType::CRYPTO->value),
            )
            ->get()
            ->each(function (InvestmentPurchase $purchase) use (&$totals): void {
                $bucket = match ($purchase->symbol->type) {
                    InvestmentSymbolType::BOND => 'bond_amount',
                    InvestmentSymbolType::ETF => 'etf_amount',
                    InvestmentSymbolType::STOCK => 'stock_amount',
                    default => null,
                };

                if ($bucket === null) {
                    return;
                }

                $totals[$bucket] += $this->quantityValueInCents(
                    $purchase->quantity,
                    $purchase->symbol->current_price,
                );
            });

        $totals['crypto_amount'] = CryptoBalance::with([
            'provider:id,supported_symbol_types',
            'symbol:id,type,current_price',
        ])
            ->get()
            ->filter(fn (CryptoBalance $balance): bool => $balance->provider->supportsCrypto()
                && $balance->symbol->type === InvestmentSymbolType::CRYPTO)
            ->sum(fn (CryptoBalance $balance): int => $this->quantityValueInCents(
                $balance->manual_quantity,
                $balance->symbol->current_price,
            ));

        return collect($totals)
            ->map(fn (int $value): string => MonthlyPortfolioSnapshot::fromCents($value))
            ->all();
    }

    /**
     * @param  array<string, string>  $currentStateTotals
     * @return array<int, array<string, mixed>>
     */
    private function buildSummaryCards(
        array $currentStateTotals,
        ?MonthlyPortfolioSnapshot $latestSnapshot,
    ): array {
        $currentTotals = [
            ...$currentStateTotals,
            'total_amount' => MonthlyPortfolioSnapshot::fromCents(
                $this->sumAmountsInCents($currentStateTotals),
            ),
        ];
        $comparisonLabel = $latestSnapshot instanceof MonthlyPortfolioSnapshot
            ? sprintf(
                'Primerjava z vnosom za %s.',
                $latestSnapshot->month_date?->format('j. n. Y'),
            )
            : 'Ni shranjenega mesečnega vnosa za primerjavo.';

        $liveFlows = $latestSnapshot instanceof MonthlyPortfolioSnapshot
            ? $this->growthAttribution->flowsBetween($latestSnapshot->month_date, null)
            : [];

        return collect(self::BUCKET_LABELS)
            ->map(function (string $label, string $key) use (
                $comparisonLabel,
                $currentTotals,
                $latestSnapshot,
                $liveFlows,
            ): array {
                $currentAmount = $currentTotals[$key] ?? '0.00';

                if (! $latestSnapshot instanceof MonthlyPortfolioSnapshot) {
                    return [
                        'key' => $key,
                        'label' => $label,
                        'current_amount' => $currentAmount,
                        'diff_amount' => null,
                        'diff_percentage' => null,
                        'contribution_amount' => null,
                        'market_amount' => null,
                        'tone' => 'warning',
                        'comparison_label' => $comparisonLabel,
                    ];
                }

                $previousAmount = (string) ($latestSnapshot->getAttribute($key) ?? '0.00');
                $currentAmountInCents = MonthlyPortfolioSnapshot::toCents($currentAmount);
                $previousAmountInCents = MonthlyPortfolioSnapshot::toCents($previousAmount);
                $diffInCents = $currentAmountInCents - $previousAmountInCents;
                $contributionInCents = $liveFlows[$key]['contribution'] ?? null;

                return [
                    'key' => $key,
                    'label' => $label,
                    'current_amount' => $currentAmount,
                    'diff_amount' => MonthlyPortfolioSnapshot::fromCents($diffInCents),
                    'diff_percentage' => $previousAmountInCents === 0
                        ? null
                        : number_format(
                            ($diffInCents / $previousAmountInCents) * 100,
                            2,
                            '.',
                            '',
                        ),
                    'contribution_amount' => $contributionInCents === null
                        ? null
                        : MonthlyPortfolioSnapshot::fromCents($contributionInCents),
                    'market_amount' => $contributionInCents === null
                        ? null
                        : MonthlyPortfolioSnapshot::fromCents($diffInCents - $contributionInCents),
                    'tone' => $diffInCents > 0
                        ? 'positive'
                        : ($diffInCents < 0 ? 'negative' : 'neutral'),
                    'comparison_label' => $comparisonLabel,
                ];
            })
            ->values()
            ->all();
    }

    public function capture(?CarbonInterface $monthDate = null): MonthlyPortfolioSnapshot
    {
        $normalizedMonth = $this->normalizeMonth($monthDate ?? now('Europe/Ljubljana'));
        $attributes = $this->snapshotPayload(
            $this->currentStateTotals(),
            MonthlyPortfolioSnapshot::SOURCE_SCHEDULED,
        );
        $snapshot = MonthlyPortfolioSnapshot::query()
            ->whereDate('month_date', $normalizedMonth->toDateString())
            ->first();

        if ($snapshot instanceof MonthlyPortfolioSnapshot) {
            $snapshot->update($attributes);

            return $snapshot->fresh();
        }

        return MonthlyPortfolioSnapshot::query()->create(
            array_merge($attributes, [
                'month_date' => $normalizedMonth->toDateString(),
            ]),
        );
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function storeManual(array $validated): MonthlyPortfolioSnapshot
    {
        return MonthlyPortfolioSnapshot::query()->create(
            array_merge(
                $this->snapshotPayload($validated, MonthlyPortfolioSnapshot::SOURCE_MANUAL),
                ['month_date' => $this->normalizeMonth($validated['month_date'])->toDateString()],
            ),
        );
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function updateManual(
        MonthlyPortfolioSnapshot $monthlySnapshot,
        array $validated,
    ): void {
        $monthlySnapshot->update(
            array_merge(
                $this->snapshotPayload($validated, MonthlyPortfolioSnapshot::SOURCE_MANUAL),
                ['month_date' => $this->normalizeMonth($validated['month_date'])->toDateString()],
            ),
        );
    }

    /**
     * @param  array<string, mixed>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function buildChartSeries(array $rows): array
    {
        $seriesColors = [
            'savings_amount' => '#2563eb',
            'bond_amount' => '#f97316',
            'etf_amount' => '#ef4444',
            'crypto_amount' => '#f59e0b',
            'stock_amount' => '#8b5cf6',
            'total_amount' => '#16a34a',
        ];

        return collect($seriesColors)
            ->map(fn (string $color, string $key): array => [
                'key' => $key,
                'label' => self::BUCKET_LABELS[$key],
                'color' => $color,
                'values' => array_map(fn (array $row): float => (float) $row[$key], $rows),
            ])
            ->values()
            ->all();
    }

    private function normalizeMonth(CarbonInterface|string $monthDate): CarbonImmutable
    {
        return CarbonImmutable::parse($monthDate)->startOfMonth();
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, string>
     */
    private function snapshotPayload(array $attributes, string $source): array
    {
        $savingsAmount = MonthlyPortfolioSnapshot::toCents($attributes['savings_amount'] ?? 0);
        $bondAmount = MonthlyPortfolioSnapshot::toCents($attributes['bond_amount'] ?? 0);
        $etfAmount = MonthlyPortfolioSnapshot::toCents($attributes['etf_amount'] ?? 0);
        $cryptoAmount = MonthlyPortfolioSnapshot::toCents($attributes['crypto_amount'] ?? 0);
        $stockAmount = MonthlyPortfolioSnapshot::toCents($attributes['stock_amount'] ?? 0);

        return [
            'savings_amount' => MonthlyPortfolioSnapshot::fromCents($savingsAmount),
            'bond_amount' => MonthlyPortfolioSnapshot::fromCents($bondAmount),
            'etf_amount' => MonthlyPortfolioSnapshot::fromCents($etfAmount),
            'crypto_amount' => MonthlyPortfolioSnapshot::fromCents($cryptoAmount),
            'stock_amount' => MonthlyPortfolioSnapshot::fromCents($stockAmount),
            'total_amount' => MonthlyPortfolioSnapshot::fromCents(
                $savingsAmount + $bondAmount + $etfAmount + $cryptoAmount + $stockAmount,
            ),
            'source' => $source,
        ];
    }

    /**
     * @param  array<string, array<string, array{contribution: int, fee: int}>>  $flowsByMonth
     * @return array<string, mixed>
     */
    private function transformSnapshot(
        MonthlyPortfolioSnapshot $snapshot,
        ?MonthlyPortfolioSnapshot $previousSnapshot,
        array $flowsByMonth,
    ): array {
        $diffAmountInCents = $previousSnapshot instanceof MonthlyPortfolioSnapshot
            ? MonthlyPortfolioSnapshot::toCents($snapshot->total_amount)
                - MonthlyPortfolioSnapshot::toCents($previousSnapshot->total_amount)
            : null;

        $previousTotalInCents = $previousSnapshot instanceof MonthlyPortfolioSnapshot
            ? MonthlyPortfolioSnapshot::toCents($previousSnapshot->total_amount)
            : null;

        return [
            'id' => $snapshot->id,
            'month_date' => $snapshot->month_date?->toDateString(),
            'month_label' => $snapshot->month_date?->format('j. n. Y'),
            'savings_amount' => $snapshot->savings_amount,
            'bond_amount' => $snapshot->bond_amount,
            'etf_amount' => $snapshot->etf_amount,
            'crypto_amount' => $snapshot->crypto_amount,
            'stock_amount' => $snapshot->stock_amount,
            'total_amount' => $snapshot->total_amount,
            'source' => $snapshot->source,
            'source_label' => $snapshot->source === MonthlyPortfolioSnapshot::SOURCE_SCHEDULED
                ? 'Samodejno'
                : 'Ročno',
            'diff_amount' => $diffAmountInCents === null
                ? null
                : MonthlyPortfolioSnapshot::fromCents($diffAmountInCents),
            'diff_percentage' => $previousTotalInCents === null || $previousTotalInCents === 0
                ? null
                : number_format(($diffAmountInCents / $previousTotalInCents) * 100, 2, '.', ''),
            'breakdown' => $this->buildGrowthBreakdown($snapshot, $previousSnapshot, $flowsByMonth),
        ];
    }

    /**
     * Split each investment bucket's month-over-month change into contributions
     * (money paid in, valued at the transaction price) and market movement
     * (everything else, including fees, dividends, coupons and staking).
     *
     * Savings is deliberately absent — there is no deposit ledger to derive it
     * from.
     *
     * @param  array<string, array<string, array{contribution: int, fee: int}>>  $flowsByMonth
     * @return array<string, mixed>
     */
    private function buildGrowthBreakdown(
        MonthlyPortfolioSnapshot $snapshot,
        ?MonthlyPortfolioSnapshot $previousSnapshot,
        array $flowsByMonth,
    ): array {
        if (! $previousSnapshot instanceof MonthlyPortfolioSnapshot
            || $previousSnapshot->month_date === null
            || $snapshot->month_date === null
        ) {
            return ['available' => false, 'types' => [], 'investments' => null];
        }

        $flows = $this->growthAttribution->sumMonthlyFlows(
            $flowsByMonth,
            $previousSnapshot->month_date,
            $snapshot->month_date,
        );
        $types = [];
        $investmentsInCents = ['diff' => 0, 'contribution' => 0, 'market' => 0, 'fee' => 0];

        foreach ($this->growthAttribution->bucketKeys() as $key) {
            $diffInCents = MonthlyPortfolioSnapshot::toCents($snapshot->getAttribute($key))
                - MonthlyPortfolioSnapshot::toCents($previousSnapshot->getAttribute($key));
            $contributionInCents = $flows[$key]['contribution'] ?? 0;
            $feeInCents = $flows[$key]['fee'] ?? 0;
            $marketInCents = $diffInCents - $contributionInCents;

            $types[] = [
                'key' => $key,
                'label' => self::BUCKET_LABELS[$key],
                'diff_amount' => MonthlyPortfolioSnapshot::fromCents($diffInCents),
                'contribution_amount' => MonthlyPortfolioSnapshot::fromCents($contributionInCents),
                'market_amount' => MonthlyPortfolioSnapshot::fromCents($marketInCents),
                'fee_amount' => MonthlyPortfolioSnapshot::fromCents($feeInCents),
            ];

            $investmentsInCents['diff'] += $diffInCents;
            $investmentsInCents['contribution'] += $contributionInCents;
            $investmentsInCents['market'] += $marketInCents;
            $investmentsInCents['fee'] += $feeInCents;
        }

        return [
            'available' => true,
            'types' => $types,
            'investments' => [
                'label' => 'Naložbe skupaj',
                'diff_amount' => MonthlyPortfolioSnapshot::fromCents($investmentsInCents['diff']),
                'contribution_amount' => MonthlyPortfolioSnapshot::fromCents($investmentsInCents['contribution']),
                'market_amount' => MonthlyPortfolioSnapshot::fromCents($investmentsInCents['market']),
                'fee_amount' => MonthlyPortfolioSnapshot::fromCents($investmentsInCents['fee']),
            ],
        ];
    }

    private function quantityValueInCents(string|int|float $quantity, string|int|float $pricePerUnit): int
    {
        return (int) round(((float) $quantity) * ((float) $pricePerUnit) * 100);
    }

    /** @param  array<string, string>  $amounts */
    private function sumAmountsInCents(array $amounts): int
    {
        return collect($amounts)
            ->sum(fn (string $amount): int => MonthlyPortfolioSnapshot::toCents($amount));
    }
}
