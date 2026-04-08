<?php

namespace App\Http\Controllers;

use App\Enums\Employee;
use App\Http\Requests\StoreSavingsAccountRequest;
use App\Http\Requests\UpdateSavingsAccountRequest;
use App\Models\SavingsAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class SavingsAccountController extends Controller
{
    public function index(): Response
    {
        $accounts = SavingsAccount::query()
            ->roots()
            ->with('children')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        return Inertia::render('Varcevanje/Index', [
            'accounts' => $accounts->map(fn (SavingsAccount $account): array => $this->transformAccount($account))->values()->all(),
            'ownerOptions' => collect(Employee::cases())->map(fn (Employee $employee): array => [
                'value' => $employee->value,
                'label' => $employee->label(),
            ])->values()->all(),
            'totals' => [
                'amount' => SavingsAccount::fromCents(
                    $accounts->sum(fn (SavingsAccount $account): int => SavingsAccount::toCents($account->amount)),
                ),
                'annual_yield' => SavingsAccount::fromCents(
                    $accounts->sum(fn (SavingsAccount $account): int => $this->yieldInCents($account, 12)),
                ),
                'monthly_yield' => SavingsAccount::fromCents(
                    $accounts->sum(fn (SavingsAccount $account): int => $this->yieldInCents($account, 1)),
                ),
            ],
        ]);
    }

    public function store(StoreSavingsAccountRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        DB::transaction(function () use ($validated): void {
            $account = SavingsAccount::query()->create($validated);

            if ($account->parent !== null) {
                $account->parent()->lockForUpdate()->first()?->syncAmountFromChildren();
            }
        });

        return back();
    }

    public function update(UpdateSavingsAccountRequest $request, SavingsAccount $savingsAccount): RedirectResponse
    {
        $validated = $request->validated();
        $originalParentId = $savingsAccount->parent_id;

        DB::transaction(function () use ($validated, $savingsAccount, $originalParentId): void {
            $account = SavingsAccount::query()->lockForUpdate()->findOrFail($savingsAccount->id);
            $account->update($validated);

            if ($originalParentId !== null && $originalParentId !== $account->parent_id) {
                SavingsAccount::query()->lockForUpdate()->find($originalParentId)?->syncAmountFromChildren();
            }

            if ($account->parent_id !== null) {
                $account->parent()->lockForUpdate()->first()?->syncAmountFromChildren();
            }

            if ($account->hasChildren()) {
                $account->syncAmountFromChildren();
            }
        });

        return back();
    }

    public function destroy(SavingsAccount $savingsAccount): RedirectResponse
    {
        $parentId = $savingsAccount->parent_id;

        DB::transaction(function () use ($savingsAccount, $parentId): void {
            SavingsAccount::query()->lockForUpdate()->findOrFail($savingsAccount->id)->delete();

            if ($parentId !== null) {
                SavingsAccount::query()->lockForUpdate()->find($parentId)?->syncAmountFromChildren();
            }
        });

        return back();
    }

    /** @return array<string, mixed> */
    private function transformAccount(SavingsAccount $account): array
    {
        return [
            'id' => $account->id,
            'parent_id' => $account->parent_id,
            'name' => $account->name,
            'owner' => $account->owner->value,
            'owner_label' => $account->owner->label(),
            'amount' => $account->amount,
            'apy' => $account->apy,
            'sort_order' => $account->sort_order,
            'annual_yield' => SavingsAccount::fromCents($this->yieldInCents($account, 12)),
            'monthly_yield' => SavingsAccount::fromCents($this->yieldInCents($account, 1)),
            'has_children' => $account->children->isNotEmpty(),
            'children' => $account->children->map(fn (SavingsAccount $child): array => $this->transformAccount($child))->values()->all(),
        ];
    }

    private function yieldInCents(SavingsAccount $account, int $divisor): int
    {
        return (int) round(
            SavingsAccount::toCents($account->amount) * ((float) $account->apy / 100) / $divisor,
        );
    }
}
