<?php

namespace App\Http\Controllers;

use App\Enums\InvestmentSymbolType;
use App\Http\Requests\UpdateInvestmentProviderRequest;
use App\Models\InvestmentProvider;
use App\Models\InvestmentSymbol;
use App\Models\SavingsAccount;
use App\Services\InvestmentPortfolioService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class InvestmentProviderController extends Controller
{
    public function show(
        InvestmentProvider $investmentProvider,
        InvestmentPortfolioService $portfolioService,
    ): Response {
        $investmentProvider->load([
            'linkedSavingsAccount.parent',
            'purchases' => fn ($query) => $query->whereHas(
                'symbol',
                fn ($query) => $query->where('type', '!=', InvestmentSymbolType::CRYPTO->value),
            ),
            'purchases.symbol',
        ]);

        $nonCryptoTypes = collect($investmentProvider->supportedSymbolTypes())
            ->reject(fn (InvestmentSymbolType $type): bool => $type === InvestmentSymbolType::CRYPTO)
            ->map(fn (InvestmentSymbolType $type): string => $type->value)
            ->all();

        return Inertia::render('Investicije/Provider', [
            'provider' => [
                'id' => $investmentProvider->id,
                'slug' => $investmentProvider->slug,
                'name' => $investmentProvider->name,
                'linked_savings_account_id' => $investmentProvider->linked_savings_account_id,
                'linked_savings_account_name' => $investmentProvider->linkedSavingsAccount?->name,
                'linked_savings_account_balance' => $investmentProvider->linkedSavingsAccount?->amount,
                'requires_linked_savings_account' => $investmentProvider->requiresLinkedSavingsAccount(),
            ],
            'summary' => $portfolioService->summarizeProvider($investmentProvider),
            'symbolSummary' => $portfolioService->summarizeProviderBySymbol($investmentProvider),
            'purchases' => $portfolioService->transformPurchases($investmentProvider->purchases),
            'symbolOptions' => InvestmentSymbol::query()
                ->whereIn('type', $nonCryptoTypes)
                ->orderBy('symbol')
                ->get()
                ->map(fn (InvestmentSymbol $symbol): array => [
                    'id' => $symbol->id,
                    'symbol' => $symbol->symbol,
                    'type' => $symbol->type->value,
                    'type_label' => $symbol->type->label(),
                    'label' => sprintf(
                        '%s (%s)',
                        $symbol->symbol,
                        $symbol->type->label(),
                    ),
                    'current_price' => $symbol->current_price,
                    'taxable' => $symbol->taxable,
                ])
                ->values()
                ->all(),
            'savingsAccountOptions' => $this->leafSavingsAccountOptions(),
        ]);
    }

    public function update(
        UpdateInvestmentProviderRequest $request,
        InvestmentProvider $investmentProvider,
    ): RedirectResponse {
        $investmentProvider->update($request->validated());

        return back();
    }

    /** @return array<int, array{id: int, label: string, amount: string}> */
    private function leafSavingsAccountOptions(): array
    {
        return SavingsAccount::query()
            ->with('parent')
            ->doesntHave('children')
            ->orderBy('name')
            ->get()
            ->map(function (SavingsAccount $account): array {
                $label = $account->parent
                    ? sprintf('%s / %s', $account->parent->name, $account->name)
                    : $account->name;

                return [
                    'id' => $account->id,
                    'label' => $label,
                    'amount' => $account->amount,
                ];
            })
            ->values()
            ->all();
    }
}
