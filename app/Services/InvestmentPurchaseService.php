<?php

namespace App\Services;

use App\Models\InvestmentProvider;
use App\Models\InvestmentPurchase;
use App\Models\SavingsAccount;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InvestmentPurchaseService
{
    /**
     * @param  array<string, mixed>  $validated
     */
    public function store(InvestmentProvider $provider, array $validated): InvestmentPurchase
    {
        return DB::transaction(function () use ($provider, $validated): InvestmentPurchase {
            $provider = $this->lockProvider($provider);

            $this->applySavingsDebit($provider, $this->cashOutflowInCents($validated));

            return $provider->purchases()->create($validated);
        });
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function update(
        InvestmentProvider $provider,
        InvestmentPurchase $investmentPurchase,
        array $validated,
    ): void {
        DB::transaction(function () use ($provider, $investmentPurchase, $validated): void {
            $provider = $this->lockProvider($provider);
            $purchase = InvestmentPurchase::query()
                ->whereBelongsTo($provider, 'provider')
                ->lockForUpdate()
                ->findOrFail($investmentPurchase->id);

            $deltaInCents = $this->cashOutflowInCents($validated) - $this->cashOutflowInCents([
                'quantity' => $purchase->quantity,
                'price_per_unit' => $purchase->price_per_unit,
                'fee' => $purchase->fee,
            ]);

            $this->applySavingsDebit($provider, $deltaInCents);

            $purchase->update($validated);
        });
    }

    public function destroy(InvestmentProvider $provider, InvestmentPurchase $investmentPurchase): void
    {
        DB::transaction(function () use ($provider, $investmentPurchase): void {
            $provider = $this->lockProvider($provider);
            $purchase = InvestmentPurchase::query()
                ->whereBelongsTo($provider, 'provider')
                ->lockForUpdate()
                ->findOrFail($investmentPurchase->id);

            $this->applySavingsDebit(
                $provider,
                -$this->cashOutflowInCents([
                    'quantity' => $purchase->quantity,
                    'price_per_unit' => $purchase->price_per_unit,
                    'fee' => $purchase->fee,
                ]),
            );

            $purchase->delete();
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function cashOutflowInCents(array $payload): int
    {
        return (int) round(
            ((float) $payload['quantity']) * ((float) $payload['price_per_unit']) * 100,
        ) + $this->toCents($payload['fee'] ?? 0);
    }

    private function applySavingsDebit(InvestmentProvider $provider, int $debitInCents): void
    {
        if (! $provider->requiresLinkedSavingsAccount()) {
            return;
        }

        if ($provider->linked_savings_account_id === null) {
            throw ValidationException::withMessages([
                'investment_symbol_id' => 'IBKR mora imeti povezan varčevalni račun.',
            ]);
        }

        $account = SavingsAccount::query()
            ->lockForUpdate()
            ->findOrFail($provider->linked_savings_account_id);

        if ($account->children()->exists()) {
            throw ValidationException::withMessages([
                'investment_symbol_id' => 'Povezan račun mora biti leaf račun brez podračunov.',
            ]);
        }

        $newBalanceInCents = SavingsAccount::toCents($account->amount) - $debitInCents;

        if ($newBalanceInCents < 0) {
            throw ValidationException::withMessages([
                'quantity' => 'Nakup presega stanje povezanega IBKR računa.',
            ]);
        }

        $account->update([
            'amount' => SavingsAccount::fromCents($newBalanceInCents),
        ]);

        if ($account->parent_id !== null) {
            SavingsAccount::query()
                ->lockForUpdate()
                ->find($account->parent_id)?->syncAmountFromChildren();
        }
    }

    private function lockProvider(InvestmentProvider $provider): InvestmentProvider
    {
        return InvestmentProvider::query()
            ->with('linkedSavingsAccount')
            ->lockForUpdate()
            ->findOrFail($provider->id);
    }

    private function toCents(string|int|float|null $amount): int
    {
        return (int) round(((float) ($amount ?? 0)) * 100);
    }
}
