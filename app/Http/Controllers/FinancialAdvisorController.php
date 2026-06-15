<?php

namespace App\Http\Controllers;

use App\Enums\AdvisorProvider;
use App\Jobs\GenerateFinancialAdvisorReport;
use App\Services\FinancialAdvisorReportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class FinancialAdvisorController extends Controller
{
    public function index(Request $request, FinancialAdvisorReportService $reports): Response
    {
        $reportId = $request->integer('report');

        return Inertia::render('Svetovalec', [
            'report' => $reportId ? $reports->find($reportId) : $reports->latest(),
            'history' => $reports->history(),
            'isGenerating' => $reports->isGenerating(),
        ]);
    }

    public function generate(Request $request, FinancialAdvisorReportService $reports): RedirectResponse
    {
        $validated = $request->validate([
            'provider' => ['nullable', Rule::enum(AdvisorProvider::class)],
        ]);

        if (! $reports->isGenerating()) {
            $provider = AdvisorProvider::tryFrom($validated['provider'] ?? '') ?? AdvisorProvider::Anthropic;

            $reports->markGenerating();

            GenerateFinancialAdvisorReport::dispatch($provider);
        }

        return back();
    }
}
