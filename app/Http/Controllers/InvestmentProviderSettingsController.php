<?php

namespace App\Http\Controllers;

use App\Enums\BalanceSyncProvider;
use App\Enums\InvestmentSymbolType;
use App\Http\Requests\StoreInvestmentProviderSettingsRequest;
use App\Http\Requests\UpdateInvestmentProviderSettingsRequest;
use App\Models\InvestmentProvider;
use App\Models\SavingsAccount;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class InvestmentProviderSettingsController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Investicije/Ponudniki', [
            'providers' => InvestmentProvider::query()
                ->with('linkedSavingsAccount.parent')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get()
                ->map(fn (InvestmentProvider $provider): array => $this->transformProviderForIndex($provider))
                ->values()
                ->all(),
        ]);
    }

    public function create(): Response
    {
        return $this->formPage();
    }

    public function edit(InvestmentProvider $investmentProvider): Response
    {
        return $this->formPage($investmentProvider);
    }

    public function store(StoreInvestmentProviderSettingsRequest $request): RedirectResponse
    {
        InvestmentProvider::query()->create($request->validated());

        return redirect()->route('investments.providers.index');
    }

    public function update(
        UpdateInvestmentProviderSettingsRequest $request,
        InvestmentProvider $investmentProvider,
    ): RedirectResponse {
        $investmentProvider->update($request->validated());

        return redirect()->route('investments.providers.index');
    }

    private function formPage(?InvestmentProvider $investmentProvider = null): Response
    {
        $investmentProvider?->loadMissing('linkedSavingsAccount.parent');

        return Inertia::render('Investicije/PonudnikiForm', [
            'provider' => $investmentProvider ? $this->transformProviderForForm($investmentProvider) : null,
            'typeOptions' => collect(InvestmentSymbolType::cases())
                ->map(fn (InvestmentSymbolType $type): array => [
                    'value' => $type->value,
                    'label' => $type->label(),
                ])
                ->values()
                ->all(),
            'syncProviderOptions' => collect(BalanceSyncProvider::cases())
                ->map(fn (BalanceSyncProvider $provider): array => [
                    'value' => $provider->value,
                    'label' => $provider->label(),
                ])
                ->values()
                ->all(),
            'savingsAccountOptions' => $this->leafSavingsAccountOptions(),
        ]);
    }

    /** @return array<string, mixed> */
    private function transformProviderForIndex(InvestmentProvider $provider): array
    {
        return [
            'id' => $provider->id,
            'slug' => $provider->slug,
            'name' => $provider->name,
            'sort_order' => $provider->sort_order,
            'requires_linked_savings_account' => $provider->requiresLinkedSavingsAccount(),
            'linked_savings_account_name' => $this->linkedSavingsAccountLabel($provider),
            'supported_symbol_type_labels' => collect($provider->supportedSymbolTypes())
                ->map(fn (InvestmentSymbolType $type): string => $type->label())
                ->values()
                ->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function transformProviderForForm(InvestmentProvider $provider): array
    {
        return [
            'id' => $provider->id,
            'slug' => $provider->slug,
            'name' => $provider->name,
            'sort_order' => $provider->sort_order,
            'linked_savings_account_id' => $provider->linked_savings_account_id,
            'requires_linked_savings_account' => $provider->requiresLinkedSavingsAccount(),
            'supported_symbol_types' => $provider->supported_symbol_types ?? [],
            'balance_sync_provider' => $provider->balanceSyncProvider()?->value,
        ];
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

    private function linkedSavingsAccountLabel(InvestmentProvider $provider): ?string
    {
        if ($provider->linkedSavingsAccount === null) {
            return null;
        }

        if ($provider->linkedSavingsAccount->parent === null) {
            return $provider->linkedSavingsAccount->name;
        }

        return sprintf(
            '%s / %s',
            $provider->linkedSavingsAccount->parent->name,
            $provider->linkedSavingsAccount->name,
        );
    }
}
