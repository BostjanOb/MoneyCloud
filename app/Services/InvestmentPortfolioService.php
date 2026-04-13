<?php

namespace App\Services;

use App\Enums\InvestmentTransactionType;
use App\Models\InvestmentProvider;
use App\Models\InvestmentPurchase;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class InvestmentPortfolioService
{
    /**
     * @return array{
     *     price: string,
     *     current_value: string,
     *     unit_diff_percentage: string,
     *     profit_loss: string,
     *     profit_loss_after_tax: string,
     *     tax_liability: string
     * }
     */
    public function calculateMetrics(
        InvestmentPurchase $purchase,
        ?CarbonInterface $asOf = null,
    ): array {
        $purchase->loadMissing('symbol');

        $asOf ??= now();

        $direction = $purchase->transactionType()->multiplier();
        $grossValueInCents = $this->quantityValueInCents(
            $purchase->quantity,
            $purchase->price_per_unit,
        );
        $priceInCents = $grossValueInCents * $direction;
        $currentValueInCents = $this->quantityValueInCents(
            $purchase->quantity,
            $purchase->symbol->current_price,
        ) * $direction;
        $feeInCents = $this->toCents($purchase->fee);
        $capitalGainInCents = $currentValueInCents - $priceInCents;
        $profitLossInCents = $capitalGainInCents - $feeInCents;
        $taxInCents = $this->taxLiabilityInCents(
            $purchase,
            $capitalGainInCents,
            $priceInCents,
            $currentValueInCents,
            $asOf,
        );

        return [
            'price' => $this->fromCents($priceInCents),
            'current_value' => $this->fromCents($currentValueInCents),
            'unit_diff_percentage' => $this->formatDecimal(
                $this->unitDiffPercentage(
                    $purchase->price_per_unit,
                    $purchase->symbol->current_price,
                ),
            ),
            'profit_loss' => $this->fromCents($profitLossInCents),
            'profit_loss_after_tax' => $this->fromCents($profitLossInCents - $taxInCents),
            'tax_liability' => $this->fromCents($taxInCents),
        ];
    }

    /**
     * @return array{
     *     total_invested: string,
     *     current_value: string,
     *     profit_loss: string,
     *     profit_loss_after_tax: string,
     *     total_fees: string,
     *     purchase_count: int
     * }
     */
    public function summarizeProvider(InvestmentProvider $provider): array
    {
        $provider->loadMissing('purchases.symbol');

        $rows = $provider->purchases->map(
            fn (InvestmentPurchase $purchase): array => $this->calculateMetrics($purchase),
        );

        return [
            'total_invested' => $this->fromCents(
                $rows->sum(fn (array $row): int => $this->toCents($row['price'])),
            ),
            'current_value' => $this->fromCents(
                $rows->sum(fn (array $row): int => $this->toCents($row['current_value'])),
            ),
            'profit_loss' => $this->fromCents(
                $rows->sum(fn (array $row): int => $this->toCents($row['profit_loss'])),
            ),
            'profit_loss_after_tax' => $this->fromCents(
                $rows->sum(fn (array $row): int => $this->toCents($row['profit_loss_after_tax'])),
            ),
            'total_fees' => $this->fromCents(
                $provider->purchases->sum(
                    fn (InvestmentPurchase $purchase): int => $this->toCents($purchase->fee),
                ),
            ),
            'purchase_count' => $provider->purchases->count(),
        ];
    }

    /**
     * @return array<int, array{
     *     symbol: string,
     *     type_label: string,
     *     current_value: string,
     *     return_percentage: string,
     *     quantity: string,
     *     total_invested: string,
     *     profit_loss: string
     * }>
     */
    public function summarizeProviderBySymbol(InvestmentProvider $provider): array
    {
        $provider->loadMissing('purchases.symbol');

        return $provider->purchases
            ->groupBy('investment_symbol_id')
            ->map(function (Collection $purchases): array {
                /** @var InvestmentPurchase $firstPurchase */
                $firstPurchase = $purchases->firstOrFail();

                $totals = $purchases->reduce(
                    function (array $carry, InvestmentPurchase $purchase): array {
                        $metrics = $this->calculateMetrics($purchase);

                        $carry['current_value'] += $this->toCents($metrics['current_value']);
                        $carry['total_invested'] += $this->toCents($metrics['price']);
                        $carry['profit_loss'] += $this->toCents($metrics['profit_loss']);
                        $carry['quantity'] += $purchase->signedQuantity();

                        return $carry;
                    },
                    [
                        'current_value' => 0,
                        'total_invested' => 0,
                        'profit_loss' => 0,
                        'quantity' => 0.0,
                    ],
                );

                $returnPercentage = $totals['total_invested'] === 0
                    ? 0.0
                    : ($totals['profit_loss'] / $totals['total_invested']) * 100;

                return [
                    'symbol' => $firstPurchase->symbol->symbol,
                    'type_label' => $firstPurchase->symbol->type->label(),
                    'current_value' => $this->fromCents($totals['current_value']),
                    'return_percentage' => $this->formatDecimal($returnPercentage),
                    'quantity' => number_format($totals['quantity'], 8, '.', ''),
                    'total_invested' => $this->fromCents($totals['total_invested']),
                    'profit_loss' => $this->fromCents($totals['profit_loss']),
                ];
            })
            ->sortBy('symbol')
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, InvestmentPurchase>  $purchases
     * @return array<int, array<string, mixed>>
     */
    public function transformPurchases(Collection $purchases): array
    {
        return $purchases->map(function (InvestmentPurchase $purchase): array {
            $purchase->loadMissing('symbol');
            $metrics = $this->calculateMetrics($purchase);

            return [
                'id' => $purchase->id,
                'investment_symbol_id' => $purchase->investment_symbol_id,
                'purchased_at' => $purchase->purchased_at?->toISOString(),
                'transaction_type' => $purchase->transactionType()->value,
                'transaction_type_label' => $purchase->transactionType()->label(),
                'quantity' => $purchase->quantity,
                'price_per_unit' => $purchase->price_per_unit,
                'fee' => $purchase->fee,
                'yield' => $purchase->yield,
                'coupon_date' => $purchase->coupon_date?->toDateString(),
                'expiry_date' => $purchase->expiry_date?->toDateString(),
                'symbol' => [
                    'id' => $purchase->symbol->id,
                    'symbol' => $purchase->symbol->symbol,
                    'type' => $purchase->symbol->type->value,
                    'type_label' => $purchase->symbol->type->label(),
                    'taxable' => $purchase->symbol->taxable,
                    'current_price' => $purchase->symbol->current_price,
                    'price_source' => $purchase->symbol->price_source,
                ],
                ...$metrics,
            ];
        })->values()->all();
    }

    private function unitDiffPercentage(string|int|float $purchasePrice, string|int|float $currentPrice): float
    {
        $purchasePrice = (float) $purchasePrice;

        if ($purchasePrice === 0.0) {
            return 0.0;
        }

        return (((float) $currentPrice - $purchasePrice) / $purchasePrice) * 100;
    }

    private function taxLiabilityInCents(
        InvestmentPurchase $purchase,
        int $capitalGainInCents,
        int $priceInCents,
        int $currentValueInCents,
        CarbonInterface $asOf,
    ): int {
        if (
            $purchase->transactionType() === InvestmentTransactionType::Sell
            || ! $purchase->symbol->taxable
            || $capitalGainInCents <= 0
        ) {
            return 0;
        }

        $recognizedExpensesInCents = min(
            $capitalGainInCents,
            (int) round(($priceInCents * 0.01) + ($currentValueInCents * 0.01)),
        );
        $taxBaseInCents = max($capitalGainInCents - $recognizedExpensesInCents, 0);
        $yearsHeld = $purchase->purchased_at?->diffInYears($asOf) ?? 0;
        $taxRate = match (true) {
            $yearsHeld < 5 => 0.25,
            $yearsHeld < 10 => 0.20,
            $yearsHeld < 15 => 0.15,
            default => 0.0,
        };

        return (int) round($taxBaseInCents * $taxRate);
    }

    private function quantityValueInCents(string|int|float $quantity, string|int|float $pricePerUnit): int
    {
        return (int) round(((float) $quantity) * ((float) $pricePerUnit) * 100);
    }

    private function toCents(string|int|float|null $amount): int
    {
        return (int) round(((float) ($amount ?? 0)) * 100);
    }

    private function fromCents(int $amountInCents): string
    {
        return number_format($amountInCents / 100, 2, '.', '');
    }

    private function formatDecimal(float $value): string
    {
        return number_format($value, 2, '.', '');
    }
}
