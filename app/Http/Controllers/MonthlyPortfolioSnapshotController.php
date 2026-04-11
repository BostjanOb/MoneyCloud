<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMonthlyPortfolioSnapshotRequest;
use App\Http\Requests\UpdateMonthlyPortfolioSnapshotRequest;
use App\Models\MonthlyPortfolioSnapshot;
use App\Services\MonthlyPortfolioSnapshotService;
use Illuminate\Http\RedirectResponse;

class MonthlyPortfolioSnapshotController extends Controller
{
    public function store(
        StoreMonthlyPortfolioSnapshotRequest $request,
        MonthlyPortfolioSnapshotService $monthlyPortfolioSnapshotService,
    ): RedirectResponse {
        $monthlyPortfolioSnapshotService->storeManual($request->validated());

        return back();
    }

    public function update(
        UpdateMonthlyPortfolioSnapshotRequest $request,
        MonthlyPortfolioSnapshot $monthlySnapshot,
        MonthlyPortfolioSnapshotService $monthlyPortfolioSnapshotService,
    ): RedirectResponse {
        $monthlyPortfolioSnapshotService->updateManual($monthlySnapshot, $request->validated());

        return back();
    }
}
