<?php

namespace App\Http\Controllers;

use App\Enums\InvestmentSymbolType;
use App\Http\Requests\ImportCryptoDcaCsvRequest;
use App\Http\Requests\StoreCryptoDcaPurchaseRequest;
use App\Http\Requests\UpdateCryptoDcaPurchaseRequest;
use App\Models\InvestmentPurchase;
use App\Services\CryptoDcaPurchaseService;
use App\Services\CryptoPortfolioService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

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

    public function import(
        ImportCryptoDcaCsvRequest $request,
        CryptoDcaPurchaseService $cryptoDcaPurchaseService,
    ): RedirectResponse {
        try {
            $summary = $cryptoDcaPurchaseService->importBinanceCsv(
                $request->csvFile(),
                $request->providerId(),
                $request->shouldAddToBalance(),
            );
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('status', $this->buildImportStatus($summary));
    }

    public function destroy(InvestmentPurchase $investmentPurchase): RedirectResponse
    {
        $this->abortUnlessCryptoPurchase($investmentPurchase);

        $investmentPurchase->delete();

        return back();
    }

    /**
     * @param  array{created: int, skipped_duplicate: int, skipped_status: int, skipped_currency: int, skipped_symbols: list<string>}  $summary
     */
    private function buildImportStatus(array $summary): string
    {
        $parts = ["Uvoženih transakcij: {$summary['created']}."];

        if ($summary['skipped_duplicate'] > 0) {
            $parts[] = "Preskočenih duplikatov: {$summary['skipped_duplicate']}.";
        }

        if ($summary['skipped_status'] > 0) {
            $parts[] = "Preskočenih neuspešnih: {$summary['skipped_status']}.";
        }

        if ($summary['skipped_currency'] > 0) {
            $parts[] = "Preskočenih ne-EUR: {$summary['skipped_currency']}.";
        }

        if ($summary['skipped_symbols'] !== []) {
            $parts[] = 'Manjkajoči simboli: '.implode(', ', $summary['skipped_symbols']).'.';
        }

        return implode(' ', $parts);
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
