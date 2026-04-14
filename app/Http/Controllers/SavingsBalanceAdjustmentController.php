<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSavingsBalanceAdjustmentRequest;
use App\Models\SavingsAccount;
use App\Services\SavingsBalanceAdjustmentService;
use Illuminate\Http\RedirectResponse;

class SavingsBalanceAdjustmentController extends Controller
{
    public function store(
        StoreSavingsBalanceAdjustmentRequest $request,
        SavingsAccount $savingsAccount,
        SavingsBalanceAdjustmentService $savingsBalanceAdjustmentService,
    ): RedirectResponse {
        $savingsBalanceAdjustmentService->adjust(
            $savingsAccount,
            $request->operation(),
            $request->amount(),
            $request->relatedAccountId(),
        );

        return back();
    }
}
