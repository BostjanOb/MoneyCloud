<?php

namespace App\Services;

use App\Models\SavingsAccount;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SavingsBalanceAdjustmentService
{
    public function adjust(
        SavingsAccount $savingsAccount,
        string $operation,
        string|int|float $amount,
        ?int $relatedAccountId = null,
    ): void {
        $amountInCents = SavingsAccount::toCents($amount);

        DB::transaction(function () use ($savingsAccount, $operation, $amountInCents, $relatedAccountId): void {
            $accounts = $this->lockAccounts($savingsAccount->id, $relatedAccountId);
            $targetAccount = $accounts->get($savingsAccount->id);

            if (! $targetAccount instanceof SavingsAccount) {
                throw ValidationException::withMessages([
                    'amount' => 'Izbrani račun ne obstaja več.',
                ]);
            }

            $relatedAccount = $relatedAccountId !== null ? $accounts->get($relatedAccountId) : null;

            if ($relatedAccountId !== null && ! $relatedAccount instanceof SavingsAccount) {
                throw ValidationException::withMessages([
                    'related_account_id' => 'Izbrani drugi račun ne obstaja več.',
                ]);
            }

            $this->ensureLeafAccount($targetAccount, 'amount');

            if ($relatedAccount instanceof SavingsAccount) {
                $this->ensureLeafAccount($relatedAccount, 'related_account_id');
            }

            [$targetDeltaInCents, $relatedDeltaInCents] = $this->resolveDeltas(
                $operation,
                $amountInCents,
                $relatedAccount instanceof SavingsAccount,
            );

            $this->ensureSufficientBalance($targetAccount, $targetDeltaInCents);

            if ($relatedAccount instanceof SavingsAccount) {
                $this->ensureSufficientBalance($relatedAccount, $relatedDeltaInCents);
            }

            $this->applyDelta($targetAccount, $targetDeltaInCents);

            if ($relatedAccount instanceof SavingsAccount) {
                $this->applyDelta($relatedAccount, $relatedDeltaInCents);
            }

            $this->syncParentAccounts(collect([$targetAccount, $relatedAccount])->filter());
        }, attempts: 5);
    }

    /**
     * @return Collection<int, SavingsAccount>
     */
    private function lockAccounts(int $targetAccountId, ?int $relatedAccountId): Collection
    {
        return SavingsAccount::query()
            ->whereIn(
                'id',
                collect([$targetAccountId, $relatedAccountId])->filter()->unique()->sort()->values(),
            )
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function resolveDeltas(string $operation, int $amountInCents, bool $hasRelatedAccount): array
    {
        if ($operation === 'subtract') {
            return [-$amountInCents, $hasRelatedAccount ? $amountInCents : 0];
        }

        return [$amountInCents, $hasRelatedAccount ? -$amountInCents : 0];
    }

    private function ensureLeafAccount(SavingsAccount $account, string $errorKey): void
    {
        if (! $account->hasChildren()) {
            return;
        }

        throw ValidationException::withMessages([
            $errorKey => $errorKey === 'amount'
                ? 'Stanje lahko spreminjate le na leaf računu brez podračunov.'
                : 'Izbrani drugi račun mora biti leaf račun brez podračunov.',
        ]);
    }

    private function ensureSufficientBalance(SavingsAccount $account, int $deltaInCents): void
    {
        $newBalanceInCents = SavingsAccount::toCents($account->amount) + $deltaInCents;

        if ($newBalanceInCents >= 0) {
            return;
        }

        throw ValidationException::withMessages([
            'amount' => sprintf('Znesek presega stanje računa %s.', $account->name),
        ]);
    }

    private function applyDelta(SavingsAccount $account, int $deltaInCents): void
    {
        if ($deltaInCents === 0) {
            return;
        }

        $account->update([
            'amount' => SavingsAccount::fromCents(
                SavingsAccount::toCents($account->amount) + $deltaInCents,
            ),
        ]);
    }

    /**
     * @param  Collection<int, SavingsAccount>  $accounts
     */
    private function syncParentAccounts(Collection $accounts): void
    {
        $accounts
            ->pluck('parent_id')
            ->filter()
            ->unique()
            ->sort()
            ->each(function (int $parentId): void {
                SavingsAccount::query()
                    ->lockForUpdate()
                    ->find($parentId)?->syncAmountFromChildren();
            });
    }
}
