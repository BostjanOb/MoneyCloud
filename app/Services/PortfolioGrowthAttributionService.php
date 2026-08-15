<?php

namespace App\Services;

use App\Enums\InvestmentSymbolType;
use App\Models\InvestmentPurchase;
use App\Models\MonthlyPortfolioSnapshot;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;

/**
 * Splits portfolio growth into contributions (new money) and market movement.
 *
 * Contributions are measured as quantity × price_per_unit, signed by the
 * transaction direction, so that `razlika = vplačila + trg` holds exactly.
 * Fees are reported separately because they never become portfolio value.
 */
class PortfolioGrowthAttributionService
{
    /**
     * Snapshot bucket keys per investment type. Savings has no bucket here —
     * savings balances are mutated in place, so contribution history for them
     * is not recoverable.
     *
     * @var array<string, string>
     */
    private const BUCKET_KEYS = [
        InvestmentSymbolType::BOND->value => 'bond_amount',
        InvestmentSymbolType::ETF->value => 'etf_amount',
        InvestmentSymbolType::CRYPTO->value => 'crypto_amount',
        InvestmentSymbolType::STOCK->value => 'stock_amount',
    ];

    /**
     * Net contribution and fees per bucket for a single time window.
     *
     * @param  CarbonInterface|null  $from  Inclusive lower bound, null for no bound.
     * @param  CarbonInterface|null  $until  Exclusive upper bound, null for no bound.
     * @return array<string, array{contribution: int, fee: int}> Amounts in cents, keyed by bucket.
     */
    public function flowsBetween(?CarbonInterface $from, ?CarbonInterface $until): array
    {
        $flows = $this->emptyFlows();

        $this->transactions($from, $until)->each(
            function (InvestmentPurchase $purchase) use (&$flows): void {
                $bucket = $this->bucketKeyFor($purchase);

                if ($bucket === null) {
                    return;
                }

                $flows[$bucket]['contribution'] += $this->signedValueInCents($purchase);
                $flows[$bucket]['fee'] += MonthlyPortfolioSnapshot::toCents($purchase->fee);
            },
        );

        return $flows;
    }

    /**
     * Net contribution and fees per bucket, grouped by the month the
     * transaction falls into. Built in a single pass so a page rendering many
     * snapshots does not run one query per month.
     *
     * Keyed by `Y-m-01`, then by bucket. Amounts in cents.
     *
     * @return array<string, array<string, array{contribution: int, fee: int}>>
     */
    public function flowsGroupedByMonth(): array
    {
        $months = [];

        $this->transactions(null, null)->each(
            function (InvestmentPurchase $purchase) use (&$months): void {
                $bucket = $this->bucketKeyFor($purchase);

                if ($bucket === null || $purchase->purchased_at === null) {
                    return;
                }

                $month = $purchase->purchased_at->startOfMonth()->toDateString();
                $months[$month] ??= $this->emptyFlows();
                $months[$month][$bucket]['contribution'] += $this->signedValueInCents($purchase);
                $months[$month][$bucket]['fee'] += MonthlyPortfolioSnapshot::toCents($purchase->fee);
            },
        );

        return $months;
    }

    /**
     * Sum the pre-grouped monthly flows falling in `[$from, $until)`.
     *
     * Both bounds are normalized to the start of their month, matching the way
     * snapshots are stored, so a window is always a whole number of months.
     *
     * @param  array<string, array<string, array{contribution: int, fee: int}>>  $flowsByMonth
     * @return array<string, array{contribution: int, fee: int}>
     */
    public function sumMonthlyFlows(
        array $flowsByMonth,
        CarbonInterface $from,
        CarbonInterface $until,
    ): array {
        $fromMonth = CarbonImmutable::parse($from)->startOfMonth()->toDateString();
        $untilMonth = CarbonImmutable::parse($until)->startOfMonth()->toDateString();
        $totals = $this->emptyFlows();

        foreach ($flowsByMonth as $month => $buckets) {
            if ($month < $fromMonth || $month >= $untilMonth) {
                continue;
            }

            foreach ($buckets as $bucket => $amounts) {
                $totals[$bucket]['contribution'] += $amounts['contribution'];
                $totals[$bucket]['fee'] += $amounts['fee'];
            }
        }

        return $totals;
    }

    /** @return array<int, string> */
    public function bucketKeys(): array
    {
        return array_values(self::BUCKET_KEYS);
    }

    /** @return Collection<int, InvestmentPurchase> */
    private function transactions(?CarbonInterface $from, ?CarbonInterface $until): Collection
    {
        return InvestmentPurchase::with('symbol:id,type')
            ->when($from, fn ($query) => $query->where('purchased_at', '>=', $from))
            ->when($until, fn ($query) => $query->where('purchased_at', '<', $until))
            ->orderBy('purchased_at')
            ->orderBy('id')
            ->get(['id', 'investment_symbol_id', 'purchased_at', 'transaction_type', 'quantity', 'price_per_unit', 'fee']);
    }

    private function bucketKeyFor(InvestmentPurchase $purchase): ?string
    {
        $type = $purchase->symbol?->type;

        if (! $type instanceof InvestmentSymbolType) {
            return null;
        }

        return self::BUCKET_KEYS[$type->value] ?? null;
    }

    private function signedValueInCents(InvestmentPurchase $purchase): int
    {
        return (int) round(
            $purchase->signedQuantity() * ((float) $purchase->price_per_unit) * 100,
        );
    }

    /** @return array<string, array{contribution: int, fee: int}> */
    private function emptyFlows(): array
    {
        $flows = [];

        foreach (self::BUCKET_KEYS as $bucket) {
            $flows[$bucket] = ['contribution' => 0, 'fee' => 0];
        }

        return $flows;
    }
}
