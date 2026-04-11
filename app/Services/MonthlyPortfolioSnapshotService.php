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
     * @return array{
     *     rows: array<int, array<string, mixed>>,
     *     chartSeries: array<int, array<string, mixed>>,
     *     latest: array<string, mixed>|null
     * }
     */
    public function pageData(): array
    {
        $rows = [];
        $previousSnapshot = null;

        foreach (MonthlyPortfolioSnapshot::query()->ordered()->get() as $snapshot) {
            $rows[] = $this->transformSnapshot($snapshot, $previousSnapshot);
            $previousSnapshot = $snapshot;
        }

        return [
            'rows' => $rows,
            'chartSeries' => $this->buildChartSeries($rows),
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

        $totals['savings_amount'] = SavingsAccount::query()
            ->whereNull('parent_id')
            ->get(['id', 'amount'])
            ->sum(fn (SavingsAccount $account): int => SavingsAccount::toCents($account->amount));

        InvestmentPurchase::query()
            ->with('symbol:id,type,current_price')
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

        $totals['crypto_amount'] = CryptoBalance::query()
            ->with(['provider:id,supported_symbol_types', 'symbol:id,type,current_price'])
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
        $seriesMeta = [
            'savings_amount' => ['label' => 'Varčevanje', 'color' => '#2563eb'],
            'bond_amount' => ['label' => 'Obveznice', 'color' => '#f97316'],
            'etf_amount' => ['label' => 'ETF', 'color' => '#ef4444'],
            'crypto_amount' => ['label' => 'Kripto', 'color' => '#f59e0b'],
            'stock_amount' => ['label' => 'Delnice', 'color' => '#8b5cf6'],
            'total_amount' => ['label' => 'Skupaj', 'color' => '#16a34a'],
        ];

        return collect($seriesMeta)
            ->map(fn (array $meta, string $key): array => [
                'key' => $key,
                'label' => $meta['label'],
                'color' => $meta['color'],
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
     * @return array<string, mixed>
     */
    private function transformSnapshot(
        MonthlyPortfolioSnapshot $snapshot,
        ?MonthlyPortfolioSnapshot $previousSnapshot,
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
        ];
    }

    private function quantityValueInCents(string|int|float $quantity, string|int|float $pricePerUnit): int
    {
        return (int) round(((float) $quantity) * ((float) $pricePerUnit) * 100);
    }
}
