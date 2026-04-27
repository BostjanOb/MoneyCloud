<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCryptoBalanceRequest;
use App\Http\Requests\SyncCryptoBalanceRequest;
use App\Http\Requests\UpdateCryptoBalanceRequest;
use App\Models\CryptoBalance;
use App\Models\InvestmentProvider;
use App\Services\CryptoBalanceSyncService;
use App\Services\CryptoPortfolioService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class CryptoBalanceController extends Controller
{
    public function index(CryptoPortfolioService $cryptoPortfolioService): Response
    {
        return Inertia::render('Kripto/Stanja', [
            'providerOptions' => $cryptoPortfolioService->providerOptions(),
            'syncProviderOptions' => $cryptoPortfolioService->syncProviderOptions(),
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

    public function sync(
        SyncCryptoBalanceRequest $request,
        CryptoBalanceSyncService $cryptoBalanceSyncService,
    ): RedirectResponse {
        $provider = $request->selectedProvider();

        if (! $provider instanceof InvestmentProvider) {
            return back()->with('error', 'Izbrane platforme ni bilo mogoče najti.');
        }

        try {
            $result = $cryptoBalanceSyncService->syncProvider($provider);

            return back()->with('status', $this->syncStatusMessage($provider, $result));
        } catch (Throwable $exception) {
            return back()->with('error', $exception->getMessage());
        }
    }

    /**
     * @param  array{
     *     updated_count: int,
     *     skipped_count: int
     * }  $result
     */
    private function syncStatusMessage(InvestmentProvider $provider, array $result): string
    {
        $totalHandled = $result['updated_count'] + $result['skipped_count'];

        if ($totalHandled === 0) {
            return sprintf('%s: ni konfiguriranih stanj za sinhronizacijo.', $provider->name);
        }

        return sprintf(
            '%s: sinhroniziranih %d stanj, preskočenih %d.',
            $provider->name,
            $result['updated_count'],
            $result['skipped_count'],
        );
    }
}
