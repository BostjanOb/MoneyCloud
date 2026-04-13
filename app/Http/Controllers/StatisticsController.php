<?php

namespace App\Http\Controllers;

use App\Services\MonthlyPortfolioSnapshotService;
use App\Services\PaycheckGrowthStatisticsService;
use App\Services\YearlyInvestmentStatisticsService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class StatisticsController extends Controller
{
    public function index(): RedirectResponse
    {
        return redirect()->route('statistics.monthly-summary');
    }

    public function monthlySummary(
        MonthlyPortfolioSnapshotService $monthlyPortfolioSnapshotService,
    ): Response {
        return Inertia::render('Statistika/MesecniPovzetek', [
            ...$monthlyPortfolioSnapshotService->pageData(),
        ]);
    }

    public function yearlyInvested(
        YearlyInvestmentStatisticsService $yearlyInvestmentStatisticsService,
    ): Response {
        return Inertia::render('Statistika/LetniVlozki', [
            ...$yearlyInvestmentStatisticsService->pageData(),
        ]);
    }

    public function paycheckGrowth(
        PaycheckGrowthStatisticsService $paycheckGrowthStatisticsService,
    ): Response {
        return Inertia::render('Statistika/RastPlac', [
            ...$paycheckGrowthStatisticsService->pageData(),
        ]);
    }
}
