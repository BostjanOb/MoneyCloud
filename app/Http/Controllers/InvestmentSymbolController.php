<?php

namespace App\Http\Controllers;

use App\Enums\InvestmentSymbolType;
use App\Http\Requests\StoreInvestmentSymbolRequest;
use App\Http\Requests\UpdateInvestmentSymbolRequest;
use App\Models\InvestmentSymbol;
use App\Services\CoinMarketCapInvestmentPriceRefreshService;
use App\Services\YfApiInvestmentPriceRefreshService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class InvestmentSymbolController extends Controller
{
    public function index(Request $request): Response
    {
        $selectedType = InvestmentSymbolType::tryFrom($request->string('type')->toString());

        return Inertia::render('Investicije/Simboli', [
            'symbols' => InvestmentSymbol::query()
                ->when(
                    $selectedType,
                    fn ($query) => $query->where('type', $selectedType->value),
                )
                ->orderBy('type')
                ->orderBy('symbol')
                ->get()
                ->map(fn (InvestmentSymbol $symbol): array => $this->transformSymbol($symbol))
                ->values()
                ->all(),
            'refreshableCryptoCount' => InvestmentSymbol::query()
                ->where('type', InvestmentSymbolType::CRYPTO->value)
                ->whereNotNull('coinmarketcap_id')
                ->count(),
            'refreshableYfapiCount' => InvestmentSymbol::query()
                ->whereNotNull('yfapi_symbol')
                ->count(),
            'typeOptions' => $this->typeOptions(),
            'filters' => [
                'type' => $selectedType?->value,
            ],
        ]);
    }

    public function create(): Response
    {
        return $this->formPage();
    }

    public function edit(InvestmentSymbol $investmentSymbol): Response
    {
        return $this->formPage($investmentSymbol);
    }

    public function store(StoreInvestmentSymbolRequest $request): RedirectResponse
    {
        InvestmentSymbol::query()->create($request->validated());

        return redirect()->route('investments.symbols.index');
    }

    public function update(
        UpdateInvestmentSymbolRequest $request,
        InvestmentSymbol $investmentSymbol,
    ): RedirectResponse {
        $investmentSymbol->update($request->validated());

        return redirect()->route('investments.symbols.index');
    }

    public function destroy(InvestmentSymbol $investmentSymbol): RedirectResponse
    {
        $investmentSymbol->delete();

        return redirect()->route('investments.symbols.index');
    }

    public function refreshPrices(string $source): RedirectResponse
    {
        $service = match ($source) {
            'coinmarketcap' => app(CoinMarketCapInvestmentPriceRefreshService::class),
            'yfapi' => app(YfApiInvestmentPriceRefreshService::class),
        };

        try {
            $result = $service->refresh();

            return redirect()
                ->route('investments.symbols.index')
                ->with('status', $this->refreshStatusMessage($result));
        } catch (Throwable $exception) {
            return redirect()
                ->route('investments.symbols.index')
                ->with('error', $exception->getMessage());
        }
    }

    private function formPage(?InvestmentSymbol $investmentSymbol = null): Response
    {
        return Inertia::render('Investicije/SimboliForm', [
            'symbol' => $investmentSymbol ? $this->transformSymbol($investmentSymbol) : null,
            'typeOptions' => $this->typeOptions(),
        ]);
    }

    /** @return array<int, array{value: string, label: string}> */
    private function typeOptions(): array
    {
        return collect(InvestmentSymbolType::cases())
            ->map(fn (InvestmentSymbolType $type): array => [
                'value' => $type->value,
                'label' => $type->label(),
            ])
            ->values()
            ->all();
    }

    /** @return array<string, mixed> */
    private function transformSymbol(InvestmentSymbol $symbol): array
    {
        return [
            'id' => $symbol->id,
            'type' => $symbol->type->value,
            'type_label' => $symbol->type->label(),
            'symbol' => $symbol->symbol,
            'isin' => $symbol->isin,
            'taxable' => $symbol->taxable,
            'price_source' => $symbol->price_source,
            'coinmarketcap_id' => $symbol->coinmarketcap_id,
            'yfapi_symbol' => $symbol->yfapi_symbol,
            'current_price' => $symbol->current_price,
            'price_synced_at' => $symbol->price_synced_at?->toIso8601String(),
        ];
    }

    /**
     * @param  array{
     *     updated_count: int,
     *     skipped_count: int,
     *     failed_symbols: list<string>
     * }  $result
     */
    private function refreshStatusMessage(array $result): string
    {
        $totalHandled = $result['updated_count'] + $result['skipped_count'];

        if ($totalHandled === 0) {
            return 'Ni simbolov za osvežitev.';
        }

        $message = sprintf(
            'Osveženih %d simbolov, preskočenih %d.',
            $result['updated_count'],
            $result['skipped_count'],
        );

        if ($result['failed_symbols'] === []) {
            return $message;
        }

        return $message.' Neuspešni simboli: '.implode(', ', $result['failed_symbols']).'.';
    }
}
