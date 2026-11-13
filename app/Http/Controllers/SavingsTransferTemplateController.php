<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSavingsTransferTemplateRequest;
use App\Http\Requests\UpdateSavingsTransferTemplateRequest;
use App\Models\SavingsAccount;
use App\Models\SavingsTransferTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class SavingsTransferTemplateController extends Controller
{
    public function store(StoreSavingsTransferTemplateRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request): void {
            $template = SavingsTransferTemplate::create([
                'name' => $request->validated('name'),
                'source_account_id' => $request->validated('source_account_id'),
                'sort_order' => $request->validated('sort_order') ?? 0,
            ]);

            $this->createItems($template, $request->amountsInCentsByAccountId());
        });

        return back();
    }

    public function update(
        UpdateSavingsTransferTemplateRequest $request,
        SavingsTransferTemplate $savingsTransferTemplate,
    ): RedirectResponse {
        DB::transaction(function () use ($request, $savingsTransferTemplate): void {
            $savingsTransferTemplate->update([
                'name' => $request->validated('name'),
                'source_account_id' => $request->validated('source_account_id'),
                'sort_order' => $request->validated('sort_order') ?? $savingsTransferTemplate->sort_order,
            ]);

            $savingsTransferTemplate->items()->delete();

            $this->createItems($savingsTransferTemplate, $request->amountsInCentsByAccountId());
        });

        return back();
    }

    public function destroy(SavingsTransferTemplate $savingsTransferTemplate): RedirectResponse
    {
        $savingsTransferTemplate->delete();

        return back();
    }

    /** @param array<int, int> $amountsInCentsByAccountId */
    private function createItems(SavingsTransferTemplate $template, array $amountsInCentsByAccountId): void
    {
        foreach ($amountsInCentsByAccountId as $accountId => $amountInCents) {
            $template->items()->create([
                'savings_account_id' => $accountId,
                'amount' => SavingsAccount::fromCents($amountInCents),
            ]);
        }
    }
}
