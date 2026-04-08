<?php

namespace App\Services;

use App\Models\SavingsAccount;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SavingsInterestService
{
    public function addInterest(SavingsAccount $savingsAccount, string|int|float $amount): void
    {
        DB::transaction(function () use ($savingsAccount, $amount): void {
            $account = SavingsAccount::query()
                ->with('children')
                ->lockForUpdate()
                ->findOrFail($savingsAccount->id);

            $interestInCents = SavingsAccount::toCents($amount);

            if ($account->children->isEmpty()) {
                $account->update([
                    'amount' => SavingsAccount::fromCents(
                        SavingsAccount::toCents($account->amount) + $interestInCents,
                    ),
                ]);

                if ($account->parent !== null) {
                    $account->parent()->lockForUpdate()->first()?->syncAmountFromChildren();
                }

                return;
            }

            $shares = $this->calculateShares($account->children, $interestInCents);

            foreach ($account->children as $child) {
                $child->update([
                    'amount' => SavingsAccount::fromCents(
                        SavingsAccount::toCents($child->amount) + $shares[$child->id],
                    ),
                ]);
            }

            $account->syncAmountFromChildren();
        });
    }

    /** @param Collection<int, SavingsAccount> $children */
    private function calculateShares(Collection $children, int $interestInCents): array
    {
        $balances = $children->mapWithKeys(fn (SavingsAccount $child): array => [
            $child->id => SavingsAccount::toCents($child->amount),
        ]);

        $totalBalanceInCents = $balances->sum();
        $shares = [];
        $distributed = 0;
        $lastChildId = $children->last()?->id;

        foreach ($children as $child) {
            if ($child->id === $lastChildId) {
                $shares[$child->id] = $interestInCents - $distributed;

                continue;
            }

            if ($totalBalanceInCents === 0) {
                $share = (int) floor($interestInCents / max($children->count(), 1));
            } else {
                $share = (int) floor(
                    $interestInCents * $balances[$child->id] / $totalBalanceInCents,
                );
            }

            $shares[$child->id] = $share;
            $distributed += $share;
        }

        return $shares;
    }
}
