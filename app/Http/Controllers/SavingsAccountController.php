<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSavingsAccountRequest;
use App\Http\Requests\UpdateSavingsAccountRequest;
use App\Models\Person;
use App\Models\SavingsAccount;
use App\Models\SavingsTransferTemplate;
use App\Models\SavingsTransferTemplateItem;
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
            ->with(['person', 'children.person'])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        return Inertia::render('Varcevanje/Index', [
            'accounts' => $accounts->map(fn (SavingsAccount $account): array => $this->transformAccount($account))->values()->all(),
            'personOptions' => Person::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get()
                ->map(fn (Person $person): array => [
                    'value' => (string) $person->id,
                    'label' => $person->name,
                ])->values()->all(),
            'leafAccountOptions' => $this->leafAccountOptions(),
            'transferTemplates' => $this->transferTemplates(),
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
            $account = SavingsAccount::query()->create([
                ...$validated,
                'apy' => $validated['apy'] ?? 0,
            ]);

            if ($account->parent !== null) {
                $account->syncApyFromParent();
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
            $account->update([
                ...$validated,
                'apy' => $validated['apy'] ?? $account->apy,
            ]);

            if ($originalParentId !== null && $originalParentId !== $account->parent_id) {
                SavingsAccount::query()->lockForUpdate()->find($originalParentId)?->syncAmountFromChildren();
            }

            if ($account->parent_id !== null) {
                $account->syncApyFromParent();
                $account->parent()->lockForUpdate()->first()?->syncAmountFromChildren();
            }

            if ($account->hasChildren()) {
                $account->syncAmountFromChildren();
                $account->syncChildrenApy();
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
            'person_id' => $account->person_id,
            'person_label' => $account->person->name,
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

    /**
     * @return array<int, array{id: int, name: string, source_account_id: int|null, source_label: string|null, sort_order: int, total: string, items: array<int, array{savings_account_id: int, label: string, amount: string}>}>
     */
    private function transferTemplates(): array
    {
        return SavingsTransferTemplate::with(['items.account.parent', 'sourceAccount.parent'])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (SavingsTransferTemplate $template): array => [
                'id' => $template->id,
                'name' => $template->name,
                'source_account_id' => $template->source_account_id,
                'source_label' => $template->sourceAccount ? $this->accountLabel($template->sourceAccount) : null,
                'sort_order' => $template->sort_order,
                'total' => SavingsAccount::fromCents($template->totalInCents()),
                'items' => $template->items
                    ->map(fn (SavingsTransferTemplateItem $item): array => [
                        'savings_account_id' => $item->savings_account_id,
                        'label' => $this->accountLabel($item->account),
                        'amount' => $item->amount,
                    ])
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();
    }

    private function accountLabel(SavingsAccount $account): string
    {
        return $account->parent
            ? sprintf('%s / %s', $account->parent->name, $account->name)
            : $account->name;
    }

    /** @return array<int, array{id: int, label: string, amount: string}> */
    private function leafAccountOptions(): array
    {
        return SavingsAccount::query()
            ->with('parent')
            ->doesntHave('children')
            ->orderBy('name')
            ->get()
            ->map(fn (SavingsAccount $account): array => [
                'id' => $account->id,
                'label' => $this->accountLabel($account),
                'amount' => $account->amount,
            ])
            ->values()
            ->all();
    }
}
