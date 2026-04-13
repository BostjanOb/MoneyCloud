<?php

namespace App\Services;

use App\Enums\InvestmentSymbolType;
use App\Enums\InvestmentTransactionType;
use App\Models\CryptoBalance;
use App\Models\InvestmentProvider;
use App\Models\InvestmentPurchase;
use App\Models\InvestmentSymbol;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class CryptoPortfolioService
{
    /** @return array<int, array{id: int, slug: string, name: string}> */
    public function providerOptions(): array
    {
        return InvestmentProvider::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->filter(fn (InvestmentProvider $provider): bool => $provider->supportsCrypto())
            ->map(fn (InvestmentProvider $provider): array => [
                'id' => $provider->id,
                'slug' => $provider->slug,
                'name' => $provider->name,
            ])
            ->values()
            ->all();
    }

    /** @return array<int, array{id: int, symbol: string, label: string, current_price: string}> */
    public function symbolOptions(): array
    {
        return InvestmentSymbol::query()
            ->where('type', InvestmentSymbolType::CRYPTO->value)
            ->orderBy('symbol')
            ->get()
            ->map(fn (InvestmentSymbol $symbol): array => [
                'id' => $symbol->id,
                'symbol' => $symbol->symbol,
                'label' => $symbol->symbol,
                'current_price' => $symbol->current_price,
            ])
            ->values()
            ->all();
    }

    /** @return array<int, array<string, mixed>> */
    public function balanceRows(): array
    {
        return $this->cryptoBalances()
            ->map(fn (CryptoBalance $balance): array => $this->makeBalanceRow($balance))
            ->sortBy([
                ['provider_name', 'asc'],
                ['symbol', 'asc'],
            ])
            ->values()
            ->all();
    }

    /** @return array<int, array<string, mixed>> */
    public function balanceSymbolSummary(): array
    {
        return $this->cryptoBalances()
            ->groupBy('investment_symbol_id')
            ->map(function (Collection $balances): array {
                /** @var CryptoBalance $firstBalance */
                $firstBalance = $balances->firstOrFail();
                $quantity = (float) $balances->sum(
                    fn (CryptoBalance $balance): float => (float) $balance->manual_quantity,
                );

                return [
                    'symbol' => $firstBalance->symbol->symbol,
                    'quantity' => $this->formatQuantity($quantity),
                    'current_price' => $firstBalance->symbol->current_price,
                    'current_value' => $this->fromCents(
                        $this->quantityValueInCents($quantity, $firstBalance->symbol->current_price),
                    ),
                    'provider_count' => $balances->count(),
                ];
            })
            ->sortBy('symbol')
            ->values()
            ->all();
    }

    /** @return array<int, array<string, mixed>> */
    public function dcaSymbolGroups(): array
    {
        $purchases = $this->cryptoPurchases()
            ->with(['provider', 'symbol'])
            ->get()
            ->groupBy('investment_symbol_id');

        return InvestmentSymbol::query()
            ->where('type', InvestmentSymbolType::CRYPTO->value)
            ->whereIn('id', $purchases->keys())
            ->orderBy('symbol')
            ->get()
            ->map(function (InvestmentSymbol $symbol) use ($purchases): array {
                $symbolPurchases = $purchases->get($symbol->id, collect());
                $totalQuantity = (float) $symbolPurchases->sum(
                    fn (InvestmentPurchase $purchase): float => $purchase->signedQuantity(),
                );
                $grossBuysInCents = $symbolPurchases->sum(
                    fn (InvestmentPurchase $purchase): int => $purchase->transactionType() === InvestmentTransactionType::Buy
                        ? $this->purchaseValueInCents($purchase)
                        : 0,
                );
                $grossSellsInCents = $symbolPurchases->sum(
                    fn (InvestmentPurchase $purchase): int => $purchase->transactionType() === InvestmentTransactionType::Sell
                        ? $this->purchaseValueInCents($purchase)
                        : 0,
                );
                $feeInCents = $symbolPurchases->sum(
                    fn (InvestmentPurchase $purchase): int => $this->toCents($purchase->fee),
                );
                $buyAmountInCents = $grossBuysInCents - $grossSellsInCents;
                $currentValueInCents = $this->quantityValueInCents($totalQuantity, $symbol->current_price);
                $profitLossAmountInCents = $currentValueInCents - $buyAmountInCents - $feeInCents;

                return [
                    'symbol' => [
                        'id' => $symbol->id,
                        'symbol' => $symbol->symbol,
                        'current_price' => $symbol->current_price,
                    ],
                    'summary' => [
                        'quantity' => $this->formatQuantity($totalQuantity),
                        'buy_amount' => $this->fromCents($buyAmountInCents),
                        'current_value' => $this->fromCents($currentValueInCents),
                        'profit_loss_amount' => $this->fromCents($profitLossAmountInCents),
                        'profit_loss_percentage' => $this->formatPercentage(
                            $buyAmountInCents === 0
                                ? 0
                                : ($profitLossAmountInCents / $buyAmountInCents) * 100,
                        ),
                        'purchase_count' => $symbolPurchases->count(),
                    ],
                    'purchases' => $symbolPurchases
                        ->sortByDesc(fn (InvestmentPurchase $purchase): string => sprintf(
                            '%s-%010d',
                            $purchase->purchased_at?->format('YmdHis.u') ?? '',
                            $purchase->id,
                        ))
                        ->values()
                        ->map(fn (InvestmentPurchase $purchase): array => $this->transformDcaPurchase($purchase))
                        ->all(),
                ];
            })
            ->values()
            ->all();
    }

    /** @return Builder<InvestmentPurchase> */
    public function cryptoPurchases(): Builder
    {
        return InvestmentPurchase::query()
            ->whereHas(
                'provider',
                fn (Builder $query): Builder => $query->whereJsonContains(
                    'supported_symbol_types',
                    InvestmentSymbolType::CRYPTO->value,
                ),
            )
            ->whereHas(
                'symbol',
                fn (Builder $query): Builder => $query->where('type', InvestmentSymbolType::CRYPTO->value),
            );
    }

    /**
     * @return array<string, mixed>
     */
    private function makeBalanceRow(CryptoBalance $balance): array
    {
        $manualQuantity = (float) $balance->manual_quantity;

        return [
            'balance_id' => $balance->id,
            'provider_id' => $balance->provider->id,
            'provider_name' => $balance->provider->name,
            'provider_slug' => $balance->provider->slug,
            'symbol_id' => $balance->symbol->id,
            'symbol' => $balance->symbol->symbol,
            'current_price' => $balance->symbol->current_price,
            'manual_quantity' => $this->formatQuantity($manualQuantity),
            'current_value' => $this->fromCents(
                $this->quantityValueInCents($manualQuantity, $balance->symbol->current_price),
            ),
        ];
    }

    /** @return array<string, mixed> */
    private function transformDcaPurchase(InvestmentPurchase $purchase): array
    {
        $purchaseValueInCents = $this->purchaseValueInCents($purchase);
        $feeInCents = $this->toCents($purchase->fee);
        $transactionType = $purchase->transactionType();

        return [
            'id' => $purchase->id,
            'investment_provider_id' => $purchase->investment_provider_id,
            'investment_symbol_id' => $purchase->investment_symbol_id,
            'purchased_at' => $purchase->purchased_at?->toISOString(),
            'transaction_type' => $transactionType->value,
            'transaction_type_label' => $transactionType->label(),
            'quantity' => $purchase->quantity,
            'price_per_unit' => $purchase->price_per_unit,
            'fee' => $purchase->fee,
            'trade_value' => $this->fromCents($purchaseValueInCents),
            'net_amount' => $this->fromCents(
                $transactionType === InvestmentTransactionType::Buy
                    ? $purchaseValueInCents + $feeInCents
                    : $purchaseValueInCents - $feeInCents,
            ),
            'provider' => [
                'id' => $purchase->provider->id,
                'slug' => $purchase->provider->slug,
                'name' => $purchase->provider->name,
            ],
            'symbol' => [
                'id' => $purchase->symbol->id,
                'symbol' => $purchase->symbol->symbol,
                'current_price' => $purchase->symbol->current_price,
            ],
        ];
    }

    private function isCryptoPair(InvestmentProvider $provider, InvestmentSymbol $symbol): bool
    {
        return $provider->supportsCrypto() && $symbol->type === InvestmentSymbolType::CRYPTO;
    }

    /** @return Collection<int, CryptoBalance> */
    private function cryptoBalances(): Collection
    {
        return CryptoBalance::query()
            ->with(['provider', 'symbol'])
            ->get()
            ->filter(fn (CryptoBalance $balance): bool => $this->isCryptoPair($balance->provider, $balance->symbol))
            ->values();
    }

    private function purchaseValueInCents(InvestmentPurchase $purchase): int
    {
        return $this->quantityValueInCents($purchase->quantity, $purchase->price_per_unit);
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

    private function formatQuantity(float $quantity): string
    {
        return number_format($quantity, 8, '.', '');
    }

    private function formatPercentage(float|int $percentage): string
    {
        return number_format((float) $percentage, 2, '.', '');
    }
}
