<?php

namespace App\Services;

use App\Enums\InvestmentSymbolType;
use App\Models\InvestmentPurchase;
use App\Models\MonthlyPortfolioSnapshot;
use Illuminate\Support\Collection;

class YearlyInvestmentStatisticsService
{
    /**
     * @return array{
     *     years: array<int, int>,
     *     symbols: array<int, array<string, mixed>>,
     *     types: array<int, array{value: string, label: string}>,
     *     rows: array<int, array<string, mixed>>,
     *     totals: array<string, mixed>
     * }
     */
    public function pageData(): array
    {
        $purchases = InvestmentPurchase::buys()
            ->with('symbol:id,symbol,type')
            ->orderBy('purchased_at')
            ->orderBy('id')
            ->get();

        if ($purchases->isEmpty()) {
            return [
                'years' => [],
                'symbols' => [],
                'types' => [],
                'rows' => [],
                'totals' => [
                    'grand_total_amount' => '0.00',
                    'symbols' => [],
                    'types' => [],
                ],
            ];
        }

        $symbols = $purchases
            ->map(fn (InvestmentPurchase $purchase) => $purchase->symbol)
            ->unique('id')
            ->sortBy('symbol')
            ->values();

        $types = $this->typesPresentIn($symbols);

        $firstYear = (int) $purchases->min(fn (InvestmentPurchase $purchase): int => $purchase->purchased_at->year);
        $years = range($firstYear, now()->year);
        $rowMap = [];
        $totalSymbolMap = $this->emptySymbolMap($symbols);
        $totalTypeMap = $this->emptyTypeMap($types);
        $grandTotalInCents = 0;

        foreach ($years as $year) {
            $rowMap[$year] = [
                'year' => $year,
                'total_amount' => '0.00',
                'symbols' => $this->emptySymbolMap($symbols),
                'types' => $this->emptyTypeMap($types),
            ];
        }

        foreach ($purchases as $purchase) {
            $year = $purchase->purchased_at->year;
            $symbolKey = (string) $purchase->investment_symbol_id;
            $typeKey = $purchase->symbol->type->value;
            $amountInCents = $this->quantityValueInCents($purchase->quantity, $purchase->price_per_unit);
            $quantity = (float) $purchase->quantity;

            $rowMap[$year]['symbols'][$symbolKey]['amount'] = MonthlyPortfolioSnapshot::fromCents(
                MonthlyPortfolioSnapshot::toCents($rowMap[$year]['symbols'][$symbolKey]['amount']) + $amountInCents,
            );
            $rowMap[$year]['symbols'][$symbolKey]['quantity'] = $this->formatQuantity(
                ((float) $rowMap[$year]['symbols'][$symbolKey]['quantity']) + $quantity,
            );
            $rowMap[$year]['types'][$typeKey] = MonthlyPortfolioSnapshot::fromCents(
                MonthlyPortfolioSnapshot::toCents($rowMap[$year]['types'][$typeKey]) + $amountInCents,
            );
            $rowMap[$year]['total_amount'] = MonthlyPortfolioSnapshot::fromCents(
                MonthlyPortfolioSnapshot::toCents($rowMap[$year]['total_amount']) + $amountInCents,
            );

            $totalSymbolMap[$symbolKey]['amount'] = MonthlyPortfolioSnapshot::fromCents(
                MonthlyPortfolioSnapshot::toCents($totalSymbolMap[$symbolKey]['amount']) + $amountInCents,
            );
            $totalSymbolMap[$symbolKey]['quantity'] = $this->formatQuantity(
                ((float) $totalSymbolMap[$symbolKey]['quantity']) + $quantity,
            );
            $totalTypeMap[$typeKey] = MonthlyPortfolioSnapshot::fromCents(
                MonthlyPortfolioSnapshot::toCents($totalTypeMap[$typeKey]) + $amountInCents,
            );
            $grandTotalInCents += $amountInCents;
        }

        return [
            'years' => $years,
            'symbols' => $symbols->map(fn ($symbol): array => [
                'id' => $symbol->id,
                'symbol' => $symbol->symbol,
                'type' => $symbol->type->value,
                'type_label' => $symbol->type->label(),
            ])->all(),
            'types' => array_map(fn (InvestmentSymbolType $type): array => [
                'value' => $type->value,
                'label' => $type->label(),
            ], $types),
            'rows' => array_values($rowMap),
            'totals' => [
                'grand_total_amount' => MonthlyPortfolioSnapshot::fromCents($grandTotalInCents),
                'symbols' => $totalSymbolMap,
                'types' => $totalTypeMap,
            ],
        ];
    }

    /**
     * Asset types actually used by the given symbols, in enum declaration order.
     *
     * @param  Collection<int, mixed>  $symbols
     * @return array<int, InvestmentSymbolType>
     */
    private function typesPresentIn(Collection $symbols): array
    {
        $present = $symbols->map(fn ($symbol): InvestmentSymbolType => $symbol->type)->unique();

        return array_values(array_filter(
            InvestmentSymbolType::cases(),
            fn (InvestmentSymbolType $type): bool => $present->contains($type),
        ));
    }

    /**
     * @param  Collection<int, mixed>  $symbols
     * @return array<string, array{amount: string, quantity: string}>
     */
    private function emptySymbolMap(Collection $symbols): array
    {
        return $symbols
            ->mapWithKeys(fn ($symbol): array => [
                (string) $symbol->id => [
                    'amount' => '0.00',
                    'quantity' => $this->formatQuantity(0),
                ],
            ])
            ->all();
    }

    /**
     * @param  array<int, InvestmentSymbolType>  $types
     * @return array<string, string>
     */
    private function emptyTypeMap(array $types): array
    {
        $map = [];

        foreach ($types as $type) {
            $map[$type->value] = '0.00';
        }

        return $map;
    }

    private function quantityValueInCents(string|int|float $quantity, string|int|float $pricePerUnit): int
    {
        return (int) round(((float) $quantity) * ((float) $pricePerUnit) * 100);
    }

    private function formatQuantity(string|int|float $quantity): string
    {
        return number_format((float) $quantity, 8, '.', '');
    }
}
