<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCryptoBalanceRequest;
use App\Http\Requests\UpdateCryptoBalanceRequest;
use App\Models\CryptoBalance;
use App\Services\CryptoPortfolioService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class CryptoBalanceController extends Controller
{
    public function index(CryptoPortfolioService $cryptoPortfolioService): Response
    {
        return Inertia::render('Kripto/Stanja', [
            'providerOptions' => $cryptoPortfolioService->providerOptions(),
            'symbolOptions' => $cryptoPortfolioService->symbolOptions(),
            'balanceRows' => $cryptoPortfolioService->balanceRows(),
            'symbolSummary' => $cryptoPortfolioService->balanceSymbolSummary(),
        ]);
    }

    public function store(StoreCryptoBalanceRequest $request): RedirectResponse
    {
        CryptoBalance::query()->create($request->validated());

        return back();
    }

    public function update(
        UpdateCryptoBalanceRequest $request,
        CryptoBalance $cryptoBalance,
    ): RedirectResponse {
        $cryptoBalance->update($request->validated());

        return back();
    }

    public function destroy(CryptoBalance $cryptoBalance): RedirectResponse
    {
        $cryptoBalance->delete();

        return back();
    }
}
