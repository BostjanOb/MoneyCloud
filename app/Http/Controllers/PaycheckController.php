<?php

namespace App\Http\Controllers;

use App\Enums\Employee;
use App\Http\Requests\StorePaycheckRequest;
use App\Http\Requests\UpdatePaycheckRequest;
use App\Models\Paycheck;
use App\Models\PaycheckYear;
use App\Services\TaxCalculationService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class PaycheckController extends Controller
{
    public function index(string $employee, TaxCalculationService $taxService): Response
    {
        $employee = Employee::tryFrom($employee) ?? abort(404);

        $year = (int) request()->query('year', (string) now()->year);

        $paycheckYear = PaycheckYear::where('employee', $employee)
            ->where('year', $year)
            ->with(['paychecks', 'bonuses'])
            ->first();

        $calculation = $paycheckYear ? $taxService->calculate($paycheckYear) : null;

        $availableYears = PaycheckYear::where('employee', $employee)
            ->pluck('year')
            ->sort()
            ->values();

        return Inertia::render('Place/Index', [
            'employee' => $employee->value,
            'employeeLabel' => $employee->label(),
            'year' => $year,
            'paycheckYear' => $paycheckYear,
            'paychecks' => $paycheckYear?->paychecks ?? [],
            'bonuses' => $paycheckYear?->bonuses ?? [],
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
