<?php

namespace App\Http\Controllers;

use App\Enums\InvestmentPriceSource;
use App\Enums\InvestmentSymbolType;
use App\Http\Requests\StoreInvestmentSymbolRequest;
use App\Http\Requests\UpdateInvestmentSymbolRequest;
use App\Models\InvestmentSymbol;
use App\Services\CoinMarketCapInvestmentPriceRefreshService;
use App\Services\LjseInvestmentPriceRefreshService;
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
            'manualPriceRefreshEnabled' => config('investments.allow_price_refresh'),
            'refreshableCoinMarketCapCount' => $this->refreshableCount(InvestmentPriceSource::COINMARKETCAP),
            'refreshableYfApiCount' => $this->refreshableCount(InvestmentPriceSource::YFAPI),
            'refreshableLjseCount' => $this->refreshableCount(InvestmentPriceSource::LJSE),
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
        if (! config('investments.allow_price_refresh')) {
            return redirect()
                ->route('investments.symbols.index')
                ->with('error', 'Ročno osveževanje cen je trenutno onemogočeno.');
        }

        $service = match ($source) {
            InvestmentPriceSource::COINMARKETCAP->value => app(CoinMarketCapInvestmentPriceRefreshService::class),
            InvestmentPriceSource::YFAPI->value => app(YfApiInvestmentPriceRefreshService::class),
            InvestmentPriceSource::LJSE->value => app(LjseInvestmentPriceRefreshService::class),
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
            'priceSourceOptions' => $this->priceSourceOptions(),
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

    /** @return array<int, array{value: string, label: string, supported_types: list<string>}> */
    private function priceSourceOptions(): array
    {
        return collect(InvestmentPriceSource::cases())
            ->map(fn (InvestmentPriceSource $source): array => [
                'value' => $source->value,
                'label' => $source->label(),
                'supported_types' => collect(InvestmentSymbolType::cases())
                    ->filter(fn (InvestmentSymbolType $type): bool => $source->supportsType($type))
                    ->map(fn (InvestmentSymbolType $type): string => $type->value)
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();
    }

    /** @return array<string, mixed> */
    private function transformSymbol(InvestmentSymbol $symbol): array
    {
        $priceSource = InvestmentPriceSource::tryFrom($symbol->price_source);

        return [
            'id' => $symbol->id,
            'type' => $symbol->type->value,
            'type_label' => $symbol->type->label(),
            'symbol' => $symbol->symbol,
            'isin' => $symbol->isin,
            'taxable' => $symbol->taxable,
            'price_source' => $symbol->price_source,
            'price_source_label' => $priceSource?->label() ?? $symbol->price_source,
            'external_source_id' => $symbol->external_source_id,
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

    private function refreshableCount(InvestmentPriceSource $source): int
    {
        return InvestmentSymbol::query()
            ->where('price_source', $source->value)
            ->whereNotNull('external_source_id')
            ->count();
    }
}
