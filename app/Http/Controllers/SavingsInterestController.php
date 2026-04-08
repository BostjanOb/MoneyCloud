<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSavingsInterestRequest;
use App\Models\SavingsAccount;
use App\Services\SavingsInterestService;
use Illuminate\Http\RedirectResponse;

class SavingsInterestController extends Controller
{
    public function store(
        StoreSavingsInterestRequest $request,
        SavingsAccount $savingsAccount,
        SavingsInterestService $savingsInterestService,
    ): RedirectResponse {
        $savingsInterestService->addInterest($savingsAccount, $request->validated('amount'));

        return back();
    }
}
