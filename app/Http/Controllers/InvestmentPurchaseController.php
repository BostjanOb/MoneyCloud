<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreInvestmentPurchaseRequest;
use App\Http\Requests\UpdateInvestmentPurchaseRequest;
use App\Models\InvestmentProvider;
use App\Models\InvestmentPurchase;
use App\Services\InvestmentPurchaseService;
use Illuminate\Http\RedirectResponse;

class InvestmentPurchaseController extends Controller
{
    public function store(
        StoreInvestmentPurchaseRequest $request,
        InvestmentProvider $investmentProvider,
        InvestmentPurchaseService $investmentPurchaseService,
    ): RedirectResponse {
        $investmentPurchaseService->store($investmentProvider, $request->validated());

        return back();
    }

    public function update(
        UpdateInvestmentPurchaseRequest $request,
        InvestmentProvider $investmentProvider,
        InvestmentPurchase $investmentPurchase,
        InvestmentPurchaseService $investmentPurchaseService,
    ): RedirectResponse {
        $investmentPurchaseService->update(
            $investmentProvider,
            $investmentPurchase,
            $request->validated(),
        );

        return back();
    }

    public function destroy(
        InvestmentProvider $investmentProvider,
        InvestmentPurchase $investmentPurchase,
        InvestmentPurchaseService $investmentPurchaseService,
    ): RedirectResponse {
        $investmentPurchaseService->destroy($investmentProvider, $investmentPurchase);

        return back();
    }
}
