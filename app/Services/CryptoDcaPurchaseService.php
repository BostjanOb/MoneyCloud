<?php

namespace App\Services;

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
                $this->addQuantityToBalance(
                    $balanceProviderId,
                    (int) $purchaseAttributes['investment_symbol_id'],
                    (string) $purchaseAttributes['quantity'],
                );
            }

            return $purchase;
        });
    }

    private function addQuantityToBalance(int $providerId, int $symbolId, string $quantity): void
    {
        $balance = CryptoBalance::query()
            ->where('investment_provider_id', $providerId)
            ->where('investment_symbol_id', $symbolId)
            ->lockForUpdate()
            ->first();

        if ($balance instanceof CryptoBalance) {
            $balance->update([
                'manual_quantity' => $this->addQuantities($balance->manual_quantity, $quantity),
            ]);

            return;
        }

        CryptoBalance::query()->create([
            'investment_provider_id' => $providerId,
            'investment_symbol_id' => $symbolId,
            'manual_quantity' => $this->formatQuantity($quantity),
        ]);
    }

    private function addQuantities(string|int|float $left, string|int|float $right): string
    {
        return $this->formatQuantity(((float) $left) + ((float) $right));
    }

    private function formatQuantity(string|int|float $quantity): string
    {
        return number_format((float) $quantity, 8, '.', '');
    }
}
