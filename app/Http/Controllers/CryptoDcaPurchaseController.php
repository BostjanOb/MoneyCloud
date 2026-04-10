<?php

namespace App\Http\Controllers;

use App\Enums\InvestmentSymbolType;
use App\Http\Requests\StoreCryptoDcaPurchaseRequest;
use App\Http\Requests\UpdateCryptoDcaPurchaseRequest;
use App\Models\InvestmentPurchase;
use App\Services\CryptoDcaPurchaseService;
use App\Services\CryptoPortfolioService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class CryptoDcaPurchaseController extends Controller
{
    public function index(CryptoPortfolioService $cryptoPortfolioService): Response
    {
        return Inertia::render('Kripto/Dca', [
            'providerOptions' => $cryptoPortfolioService->providerOptions(),
            'symbolOptions' => $cryptoPortfolioService->symbolOptions(),
            'symbolGroups' => $cryptoPortfolioService->dcaSymbolGroups(),
        ]);
    }

    public function store(
        StoreCryptoDcaPurchaseRequest $request,
        CryptoDcaPurchaseService $cryptoDcaPurchaseService,
    ): RedirectResponse {
        $cryptoDcaPurchaseService->store(
            $request->purchaseAttributes(),
            $request->shouldAddToBalance(),
            $request->balanceProviderId(),
        );

        return back();
    }

    public function update(
        UpdateCryptoDcaPurchaseRequest $request,
        InvestmentPurchase $investmentPurchase,
    ): RedirectResponse {
        $this->abortUnlessCryptoPurchase($investmentPurchase);

        $investmentPurchase->update($request->purchaseAttributes());

        return back();
    }

    public function destroy(InvestmentPurchase $investmentPurchase): RedirectResponse
    {
        $this->abortUnlessCryptoPurchase($investmentPurchase);

        $investmentPurchase->delete();

        return back();
    }

    private function abortUnlessCryptoPurchase(InvestmentPurchase $investmentPurchase): void
    {
        $investmentPurchase->loadMissing(['provider', 'symbol']);

        abort_unless(
            $investmentPurchase->provider->supportsCrypto()
            && $investmentPurchase->symbol->type === InvestmentSymbolType::CRYPTO,
            404,
        );
    }
}
