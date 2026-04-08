<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBonusRequest;
use App\Http\Requests\UpdateBonusRequest;
use App\Models\Bonus;
use Illuminate\Http\RedirectResponse;

class BonusController extends Controller
{
    public function store(StoreBonusRequest $request): RedirectResponse
    {
        Bonus::create($request->validated());

        return back();
    }

    public function update(UpdateBonusRequest $request, Bonus $bonus): RedirectResponse
    {
        $bonus->update($request->validated());

        return back();
    }

    public function destroy(Bonus $bonus): RedirectResponse
    {
        $bonus->delete();

        return back();
    }
}
