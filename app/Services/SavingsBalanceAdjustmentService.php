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
            $accounts = $this->lockAccounts([$savingsAccount->id, $relatedAccountId]);
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
     * Move money from one leaf account to several leaf accounts in a single transaction.
     *
     * @param  array<int, int>  $amountsInCentsByAccountId
     */
    public function transferToMany(SavingsAccount $sourceAccount, array $amountsInCentsByAccountId): void
    {
        DB::transaction(function () use ($sourceAccount, $amountsInCentsByAccountId): void {
            if (array_key_exists($sourceAccount->id, $amountsInCentsByAccountId)) {
                throw ValidationException::withMessages([
                    'source_account_id' => 'Izvorni račun ne sme biti med ciljnimi računi.',
                ]);
            }

            $accounts = $this->lockAccounts([$sourceAccount->id, ...array_keys($amountsInCentsByAccountId)]);
            $lockedSourceAccount = $accounts->get($sourceAccount->id);

            if (! $lockedSourceAccount instanceof SavingsAccount) {
                throw ValidationException::withMessages([
                    'source_account_id' => 'Izbrani izvorni račun ne obstaja več.',
                ]);
            }

            $this->ensureLeafAccount($lockedSourceAccount, 'source_account_id');

            $targetAccounts = collect(array_keys($amountsInCentsByAccountId))
                ->map(function (int $accountId) use ($accounts): SavingsAccount {
                    $account = $accounts->get($accountId);

                    if (! $account instanceof SavingsAccount) {
                        throw ValidationException::withMessages([
                            'source_account_id' => 'Eden od ciljnih računov ne obstaja več.',
                        ]);
                    }

                    $this->ensureLeafAccount($account, 'source_account_id');

                    return $account;
                });

            $totalInCents = array_sum($amountsInCentsByAccountId);

            $this->ensureSufficientBalance($lockedSourceAccount, -$totalInCents, 'source_account_id');
            $this->applyDelta($lockedSourceAccount, -$totalInCents);

            $targetAccounts->each(
                fn (SavingsAccount $account) => $this->applyDelta($account, $amountsInCentsByAccountId[$account->id]),
            );

            $this->syncParentAccounts($targetAccounts->push($lockedSourceAccount));
        }, attempts: 5);
    }

    /**
     * @param  array<int, int|null>  $accountIds
     * @return Collection<int, SavingsAccount>
     */
    private function lockAccounts(array $accountIds): Collection
    {
        return SavingsAccount::query()
            ->whereIn(
                'id',
                collect($accountIds)->filter()->unique()->sort()->values(),
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
            $errorKey => match ($errorKey) {
                'amount' => 'Stanje lahko spreminjate le na leaf računu brez podračunov.',
                'source_account_id' => sprintf('Račun %s ima podračune, zato ga ni mogoče uporabiti za prenos.', $account->name),
                default => 'Izbrani drugi račun mora biti leaf račun brez podračunov.',
            },
        ]);
    }

    private function ensureSufficientBalance(SavingsAccount $account, int $deltaInCents, string $errorKey = 'amount'): void
    {
        $newBalanceInCents = SavingsAccount::toCents($account->amount) + $deltaInCents;

        if ($newBalanceInCents >= 0) {
            return;
        }

        throw ValidationException::withMessages([
            $errorKey => sprintf('Znesek presega stanje računa %s.', $account->name),
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
