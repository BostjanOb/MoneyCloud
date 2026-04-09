<?php

namespace App\Http\Controllers;

use App\Enums\InvestmentSymbolType;
use App\Http\Requests\StoreInvestmentSymbolRequest;
use App\Http\Requests\UpdateInvestmentSymbolRequest;
use App\Models\InvestmentSymbol;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class InvestmentSymbolController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Investicije/Simboli', [
            'symbols' => InvestmentSymbol::query()
                ->orderBy('type')
                ->orderBy('symbol')
                ->get()
                ->map(fn (InvestmentSymbol $symbol): array => $this->transformSymbol($symbol))
                ->values()
                ->all(),
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

    private function formPage(?InvestmentSymbol $investmentSymbol = null): Response
    {
        return Inertia::render('Investicije/SimboliForm', [
            'symbol' => $investmentSymbol ? $this->transformSymbol($investmentSymbol) : null,
            'typeOptions' => collect(InvestmentSymbolType::cases())
                ->map(fn (InvestmentSymbolType $type): array => [
                    'value' => $type->value,
                    'label' => $type->label(),
                ])
                ->values()
                ->all(),
        ]);
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
            'current_price' => $symbol->current_price,
        ];
    }
}
