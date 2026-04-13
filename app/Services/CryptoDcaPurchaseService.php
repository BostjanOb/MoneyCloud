<?php

namespace App\Services;

use App\Enums\InvestmentTransactionType;
use App\Models\CryptoBalance;
use App\Models\InvestmentPurchase;
use Illuminate\Support\Facades\DB;

class CryptoDcaPurchaseService
{
    /**
     * @param  array<string, mixed>  $purchaseAttributes
     */
    public function store(
        array $purchaseAttributes,
        bool $addToBalance,
        ?int $balanceProviderId,
    ): InvestmentPurchase {
        return DB::transaction(function () use ($purchaseAttributes, $addToBalance, $balanceProviderId): InvestmentPurchase {
            $purchase = InvestmentPurchase::query()->create($purchaseAttributes);

            if ($addToBalance && $balanceProviderId !== null) {
                $this->adjustBalanceQuantity(
                    $balanceProviderId,
                    (int) $purchaseAttributes['investment_symbol_id'],
                    (string) $purchaseAttributes['quantity'],
                    InvestmentTransactionType::from((string) $purchaseAttributes['transaction_type']),
                );
            }

            return $purchase;
        });
    }

    private function adjustBalanceQuantity(
        int $providerId,
        int $symbolId,
        string $quantity,
        InvestmentTransactionType $transactionType,
    ): void {
        $balance = CryptoBalance::query()
            ->where('investment_provider_id', $providerId)
            ->where('investment_symbol_id', $symbolId)
            ->lockForUpdate()
            ->first();

        if ($balance instanceof CryptoBalance) {
            $newQuantity = $transactionType === InvestmentTransactionType::Buy
                ? $this->addQuantities($balance->manual_quantity, $quantity)
                : $this->subtractQuantities($balance->manual_quantity, $quantity);

            $balance->update([
                'manual_quantity' => $newQuantity,
            ]);

            return;
        }

        CryptoBalance::query()->create([
            'investment_provider_id' => $providerId,
            'investment_symbol_id' => $symbolId,
            'manual_quantity' => $transactionType === InvestmentTransactionType::Buy
                ? $this->formatQuantity($quantity)
                : $this->formatQuantity(0),
        ]);
    }

    private function addQuantities(string|int|float $left, string|int|float $right): string
    {
        return $this->formatQuantity(((float) $left) + ((float) $right));
    }

    private function subtractQuantities(string|int|float $left, string|int|float $right): string
    {
        return $this->formatQuantity(max(((float) $left) - ((float) $right), 0));
    }

    private function formatQuantity(string|int|float $quantity): string
    {
        return number_format((float) $quantity, 8, '.', '');
    }
}
