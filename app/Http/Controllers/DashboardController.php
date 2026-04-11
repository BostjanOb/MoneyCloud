<?php

namespace App\Http\Controllers;

use App\Services\DashboardService;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(DashboardService $dashboardService): Response
    {
        return Inertia::render('Dashboard', [
            ...$dashboardService->pageData(),
            'trend' => Inertia::defer(
                fn (): array => $dashboardService->trendData(),
                'trend',
            ),
            'investments' => Inertia::defer(
                fn (): array => $dashboardService->investmentData(),
                'investments',
            ),
        ]);
    }
}
