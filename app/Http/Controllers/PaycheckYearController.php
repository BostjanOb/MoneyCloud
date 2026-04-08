<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePaycheckYearRequest;
use App\Http\Requests\UpdatePaycheckYearRequest;
use App\Models\PaycheckYear;
use Illuminate\Http\RedirectResponse;

class PaycheckYearController extends Controller
{
    public function store(StorePaycheckYearRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        PaycheckYear::create($validated);

        return redirect()->route('place.index', [
            'employee' => $validated['employee'],
            'year' => $validated['year'],
        ]);
    }

    public function update(UpdatePaycheckYearRequest $request, PaycheckYear $paycheckYear): RedirectResponse
    {
        $paycheckYear->update($request->validated());

        return back();
    }
}
