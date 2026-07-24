<?php

namespace App\Http\Controllers;

use App\Enums\AdvisorModel;
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
            'models' => AdvisorModel::options(),
            'defaultModel' => AdvisorModel::ClaudeSonnet46->value,
        ]);
    }

    public function generate(Request $request, FinancialAdvisorReportService $reports): RedirectResponse
    {
        $validated = $request->validate([
            'model' => ['nullable', Rule::enum(AdvisorModel::class)],
        ]);

        $model = AdvisorModel::tryFrom($validated['model'] ?? '') ?? AdvisorModel::ClaudeSonnet46;

        if ($reports->tryMarkGenerating()) {
            GenerateFinancialAdvisorReport::dispatch($model);
        }

        return back();
    }
}
