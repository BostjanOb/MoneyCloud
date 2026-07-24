<?php

namespace App\Services;

use App\Ai\Agents\FinancialAnalyst;
use App\Enums\AdvisorModel;
use App\Models\FinancialAdvisorReport;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Log;

/**
 * Generates and persists the periodic structured analysis produced by the
 * {@see FinancialAnalyst} agent.
 */
class FinancialAdvisorReportService
{
    public const GENERATING_KEY = 'financial_advisor.generating';

    public function __construct(
        private readonly FinancialContextService $context,
        private readonly ActualBudgetContextService $actualBudget,
    ) {}

    /**
     * Generate a fresh analysis, persist it, and return it.
     *
     * @return array{id: int, generated_at: string, model: array{value: string, label: string}|null, usage: array<string, int>|null, report: array<string, mixed>}
     */
    public function generate(AdvisorModel $model = AdvisorModel::ClaudeSonnet46): array
    {
        try {
            [$data, $usage] = $this->actualBudget->isConfigured()
                ? $this->generateWithActualBudgetContext($model)
                : $this->generateWithDefaultContext($model);

            Log::info('advisor.report.usage', ['model' => $model->value, 'usage' => $usage]);

            $report = FinancialAdvisorReport::create([
                'generated_at' => CarbonImmutable::now('Europe/Ljubljana'),
                'model' => $model,
                'usage' => $usage,
                'report' => $data,
            ]);

            return $this->toPayload($report);
        } finally {
            Cache::forget(self::GENERATING_KEY);
        }
    }

    /**
     * Whether a report generation is currently in progress.
     */
    public function isGenerating(): bool
    {
        return (bool) Cache::get(self::GENERATING_KEY, false);
    }

    /**
     * Atomically flag that a report generation is starting.
     *
     * Returns false when a generation is already in progress, so two racing
     * requests can never dispatch the job (and burn tokens) twice. Self-expires
     * so a failed job never leaves the UI stuck in a generating state.
     */
    public function tryMarkGenerating(): bool
    {
        return Cache::add(self::GENERATING_KEY, true, now()->addMinutes(15));
    }

    /**
     * Flag that a report generation has been queued. Self-expires so a failed
     * job never leaves the UI stuck in a generating state.
     */
    public function markGenerating(): void
    {
        Cache::put(self::GENERATING_KEY, true, now()->addMinutes(15));
    }

    /**
     * The most recently generated report, if one exists.
     *
     * @return array{id: int, generated_at: string, model: array{value: string, label: string}|null, usage: array<string, int>|null, report: array<string, mixed>}|null
     */
    public function latest(): ?array
    {
        $report = FinancialAdvisorReport::latestFirst()->first();

        return $report ? $this->toPayload($report) : null;
    }

    /**
     * A specific report by id, falling back to the latest if it does not exist.
     *
     * @return array{id: int, generated_at: string, model: array{value: string, label: string}|null, usage: array<string, int>|null, report: array<string, mixed>}|null
     */
    public function find(int $id): ?array
    {
        $report = FinancialAdvisorReport::find($id);

        return $report ? $this->toPayload($report) : $this->latest();
    }

    /**
     * A lightweight list of every report for the history selector.
     *
     * @return array<int, array{id: int, generated_at: string, model: string|null}>
     */
    public function history(): array
    {
        return FinancialAdvisorReport::latestFirst()
            ->get()
            ->map(fn (FinancialAdvisorReport $report): array => [
                'id' => $report->id,
                'generated_at' => $report->generated_at->toIso8601String(),
                'model' => $report->model?->label(),
            ])
            ->all();
    }

    public function clear(): void
    {
        Cache::forget(self::GENERATING_KEY);
    }

    /**
     * Shape a stored report into the payload the frontend expects.
     *
     * @return array{id: int, generated_at: string, model: array{value: string, label: string}|null, usage: array<string, int>|null, report: array<string, mixed>}
     */
    private function toPayload(FinancialAdvisorReport $report): array
    {
        return [
            'id' => $report->id,
            'generated_at' => $report->generated_at->toIso8601String(),
            'model' => $report->model
                ? ['value' => $report->model->value, 'label' => $report->model->label()]
                : null,
            'usage' => $report->usage,
            'report' => $report->report,
        ];
    }

    /**
     * Build the prompt, embedding a compact allocation snapshot for framing.
     * The agent fetches all further detail through its tools.
     */
    private function buildPrompt(): string
    {
        $today = CarbonImmutable::now('Europe/Ljubljana')->toDateString();
        $snapshot = json_encode(
            $this->context->allocationBreakdown(),
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE,
        );

        return <<<PROMPT
        Današnji datum je {$today}. Pripravi tedensko finančno analizo gospodinjstva.

        Za začetni kontekst je trenutna razporeditev premoženja: {$snapshot}

        Z orodji pridobi vse nadaljnje podrobnosti (zgodovino, varčevanje, naložbe,
        prejemke, davke in koledar obveznic) ter pripravi celovito strukturirano analizo.
        PROMPT;
    }

    /**
     * Generate the report from MoneyCloud data only.
     *
     * @return array{0: array<string, mixed>, 1: array<string, int>}
     */
    private function generateWithDefaultContext(AdvisorModel $model): array
    {
        $response = (new FinancialAnalyst)->prompt($this->buildPrompt(), provider: $model->promptTarget());

        return [$response->toArray(), $response->usage->toArray()];
    }

    /**
     * Generate the report enriched with Actual Budget context.
     *
     * @return array{0: array<string, mixed>, 1: array<string, int>}
     */
    private function generateWithActualBudgetContext(AdvisorModel $model): array
    {
        $actualBudgetContext = $this->actualBudget->reportContext();
        $response = Context::scope(
            fn () => (new FinancialAnalyst)->prompt($this->buildActualBudgetPrompt(), provider: $model->promptTarget()),
            hidden: [ActualBudgetContextService::REPORT_CONTEXT_KEY => $actualBudgetContext],
        );

        $data = $response->toArray();

        if ($actualBudgetContext['warnings'] ?? []) {
            $data['opozorila'] = array_values(array_unique([
                ...($data['opozorila'] ?? []),
                ...$actualBudgetContext['warnings'],
            ]));
        }

        return [$data, $response->usage->toArray()];
    }

    private function buildActualBudgetPrompt(): string
    {
        return $this->buildPrompt()."\n\n".<<<'PROMPT'
        Actual Budget je nastavljen za to poročilo. Pred končno analizo uporabi Actual Budget
        orodja za pregled proračuna, 90-dnevno porabo po kategorijah in raw transakcije.
        Porabe, proračuna, kategorij in konkretnih odstopanj ne analiziraj samo iz MoneyCloud
        podatkov. Če Actual Budget ni dosegljiv in so uporabljeni predpomnjeni podatki, opozorilo
        obravnavaj kot pomembno omejitev poročila.
        PROMPT;
    }
}
