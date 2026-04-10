<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePaycheckYearRequest;
use App\Http\Requests\UpdatePaycheckYearRequest;
use App\Models\PaycheckYear;
use App\Models\Person;
use Illuminate\Http\RedirectResponse;

class PaycheckYearController extends Controller
{
    public function store(StorePaycheckYearRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        PaycheckYear::create($validated);
        $person = Person::query()->findOrFail($validated['person_id']);

        return redirect()->route('place.index', [
            'person' => $person,
            'year' => $validated['year'],
        ]);
    }

    public function update(UpdatePaycheckYearRequest $request, PaycheckYear $paycheckYear): RedirectResponse
    {
        $paycheckYear->update($request->validated());

        return back();
    }
}
