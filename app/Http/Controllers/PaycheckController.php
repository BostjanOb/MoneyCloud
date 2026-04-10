<?php

namespace App\Http\Controllers;

use App\Enums\BonusType;
use App\Http\Requests\StorePaycheckRequest;
use App\Http\Requests\UpdatePaycheckRequest;
use App\Models\Paycheck;
use App\Models\PaycheckYear;
use App\Models\Person;
use App\Services\TaxCalculationService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class PaycheckController extends Controller
{
    public function index(Person $person, TaxCalculationService $taxService): Response
    {
        $year = (int) request()->query('year', (string) now()->year);

        $paycheckYear = PaycheckYear::whereBelongsTo($person)
            ->where('year', $year)
            ->with(['paychecks', 'bonuses'])
            ->first();

        $calculation = $paycheckYear ? $taxService->calculate($paycheckYear) : null;

        $availableYears = PaycheckYear::whereBelongsTo($person)
            ->pluck('year')
            ->sortDesc()
            ->values();

        return Inertia::render('Place/Index', [
            'person' => [
                'id' => $person->id,
                'slug' => $person->slug,
                'name' => $person->name,
                'is_active' => $person->is_active,
            ],
            'year' => $year,
            'paycheckYear' => $paycheckYear,
            'paychecks' => $paycheckYear?->paychecks ?? [],
            'bonuses' => $paycheckYear?->bonuses ?? [],
            'bonusTypeOptions' => collect(BonusType::cases())
                ->map(fn (BonusType $bonusType): array => [
                    'value' => $bonusType->value,
                    'label' => $bonusType->label(),
                ])
                ->values()
                ->all(),
            'calculation' => $calculation,
            'availableYears' => $availableYears,
        ]);
    }

    public function store(StorePaycheckRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $paycheckYear = PaycheckYear::findOrFail($validated['paycheck_year_id']);

        $paycheckYear->paychecks()->create($validated);

        return back();
    }

    public function update(UpdatePaycheckRequest $request, Paycheck $paycheck): RedirectResponse
    {
        $paycheck->update($request->validated());

        return back();
    }

    public function destroy(Paycheck $paycheck): RedirectResponse
    {
        $paycheck->delete();

        return back();
    }
}
