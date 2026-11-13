<?php

namespace App\Http\Controllers;

use App\Http\Requests\ExecuteSavingsTransferTemplateRequest;
use App\Models\SavingsAccount;
use App\Models\SavingsTransferTemplate;
use App\Models\SavingsTransferTemplateItem;
use App\Services\SavingsBalanceAdjustmentService;
use Illuminate\Http\RedirectResponse;

class SavingsTransferTemplateExecutionController extends Controller
{
    public function store(
        ExecuteSavingsTransferTemplateRequest $request,
        SavingsTransferTemplate $savingsTransferTemplate,
        SavingsBalanceAdjustmentService $savingsBalanceAdjustmentService,
    ): RedirectResponse {
        $sourceAccount = $request->sourceAccount();

        $savingsBalanceAdjustmentService->transferToMany(
            $sourceAccount,
            $savingsTransferTemplate->items
                ->mapWithKeys(fn (SavingsTransferTemplateItem $item): array => [
                    $item->savings_account_id => SavingsAccount::toCents($item->amount),
                ])
                ->all(),
        );

        return back()->with('status', sprintf(
            'Predloga %s izvedena: %s € prenesenih iz računa %s.',
            $savingsTransferTemplate->name,
            number_format($savingsTransferTemplate->totalInCents() / 100, 2, ',', '.'),
            $sourceAccount->name,
        ));
    }
}
