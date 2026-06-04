<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateFinancialAdvisorReport;
use App\Services\FinancialAdvisorReportService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class FinancialAdvisorController extends Controller
{
    public function index(FinancialAdvisorReportService $reports): Response
    {
        return Inertia::render('Svetovalec', [
            'report' => $reports->latest(),
            'isGenerating' => $reports->isGenerating(),
        ]);
    }

    public function generate(FinancialAdvisorReportService $reports): RedirectResponse
    {
        if (! $reports->isGenerating()) {
            $reports->markGenerating();

            GenerateFinancialAdvisorReport::dispatch();
        }

        return back();
    }
}
