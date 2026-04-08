<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTaxSettingRequest;
use App\Http\Requests\UpdateTaxSettingRequest;
use App\Models\TaxSetting;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class TaxSettingController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Place/Nastavitve', [
            'taxSettings' => TaxSetting::orderBy('year_from', 'desc')->get(),
        ]);
    }

    public function create(): Response
    {
        return $this->formPage();
    }

    public function edit(TaxSetting $taxSetting): Response
    {
        return $this->formPage($taxSetting);
    }

    public function store(StoreTaxSettingRequest $request): RedirectResponse
    {
        TaxSetting::create($this->normalizePayload($request->validated()));

        return redirect()->route('place.nastavitve');
    }

    public function update(UpdateTaxSettingRequest $request, TaxSetting $taxSetting): RedirectResponse
    {
        $taxSetting->update($this->normalizePayload($request->validated()));

        return redirect()->route('place.nastavitve');
    }

    public function destroy(TaxSetting $taxSetting): RedirectResponse
    {
        $taxSetting->delete();

        return redirect()->route('place.nastavitve');
    }

    private function formPage(?TaxSetting $taxSetting = null): Response
    {
        return Inertia::render('Place/NastavitveForm', [
            'taxSetting' => $taxSetting,
        ]);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function normalizePayload(array $validated): array
    {
        $validated['general_relief_brackets'] = collect($validated['general_relief_brackets'])
            ->map(fn (array $bracket): array => [
                'income_from' => $bracket['income_from'],
                'income_to' => $bracket['income_to'] ?? null,
                'base_relief' => $bracket['base_relief'],
                'formula_constant' => $bracket['formula_constant'] ?? null,
                'formula_multiplier' => $bracket['formula_multiplier'] ?? null,
            ])
            ->sortBy('income_from', SORT_NUMERIC)
            ->values()
            ->all();

        $validated['brackets'] = collect($validated['brackets'])
            ->map(fn (array $bracket): array => [
                'bracket_from' => $bracket['bracket_from'],
                'bracket_to' => $bracket['bracket_to'] ?? null,
                'base_tax' => $bracket['base_tax'],
                'rate' => $bracket['rate'],
            ])
            ->sortBy('bracket_from', SORT_NUMERIC)
            ->values()
            ->all();

        return $validated;
    }
}
