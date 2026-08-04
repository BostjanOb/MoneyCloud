<?php

namespace App\Services;

use App\Ai\Agents\FinancialAnalyst;
use App\Enums\AdvisorModel;
use App\Models\FinancialAdvisorReport;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Responses\AgentResponse;
use Laravel\Ai\Responses\Data\Step;
use Laravel\Ai\Responses\Data\ToolCall;
use Laravel\Ai\Responses\StructuredAgentResponse;
use RuntimeException;

/**
 * Generates and persists the periodic structured analysis produced by the
 * {@see FinancialAnalyst} agent.
 */
class FinancialAdvisorReportService
{
    public const GENERATING_KEY = 'financial_advisor.generating';

    /**
     * How long the generating flag lives. It self-expires so a failed job never
     * leaves the UI stuck, but it must outlive the job's own timeout or the
     * flag clears mid-generation and a second run can be dispatched over it.
     */
    public const GENERATING_TTL_MINUTES = 35;

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
            [$response, $actualBudgetContext] = $this->actualBudget->isConfigured()
                ? $this->promptWithActualBudgetContext($model)
                : [$this->promptWithDefaultContext($model), null];

            $usage = $response->usage->toArray();

            Log::info('advisor.report.response', [
                'model' => $model->value,
                'usage' => $usage,
                ...$this->responseDiagnostics($response),
            ]);

            $data = $this->structuredReport($response, $model);

            if ($warnings = $actualBudgetContext['warnings'] ?? []) {
                $data['opozorila'] = array_values(array_unique([
                    ...($data['opozorila'] ?? []),
                    ...$warnings,
                ]));
            }

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
        return Cache::add(self::GENERATING_KEY, true, now()->addMinutes(self::GENERATING_TTL_MINUTES));
    }

    /**
     * Flag that a report generation has been queued. Self-expires so a failed
     * job never leaves the UI stuck in a generating state.
     */
    public function markGenerating(): void
    {
        Cache::put(self::GENERATING_KEY, true, now()->addMinutes(self::GENERATING_TTL_MINUTES));
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
     * Prompt the analyst with MoneyCloud data only.
     */
    private function promptWithDefaultContext(AdvisorModel $model): AgentResponse
    {
        return (new FinancialAnalyst)->prompt($this->buildPrompt(), provider: $model->promptTarget());
    }

    /**
     * Prompt the analyst with the Actual Budget context injected, returning the
     * response together with the context so its warnings can be merged in.
     *
     * @return array{0: AgentResponse, 1: array<string, mixed>}
     */
    private function promptWithActualBudgetContext(AdvisorModel $model): array
    {
        $actualBudgetContext = $this->actualBudget->reportContext();

        $response = Context::scope(
            fn () => (new FinancialAnalyst)->prompt($this->buildActualBudgetPrompt(), provider: $model->promptTarget()),
            hidden: [ActualBudgetContextService::REPORT_CONTEXT_KEY => $actualBudgetContext],
        );

        return [$response, $actualBudgetContext];
    }

    /**
     * Pull the structured analysis out of the agent response.
     *
     * A model that spends its whole step budget on tool calls, gets truncated by
     * the token limit, or answers with prose never emits the structured payload.
     * The SDK then hands back an empty array, which would be persisted as an
     * unrenderable report, so fail loudly with the full response logged instead.
     *
     * @return array<string, mixed>
     */
    private function structuredReport(AgentResponse $response, AdvisorModel $model): array
    {
        $data = $response instanceof StructuredAgentResponse ? $response->toArray() : [];

        if (blank($data['povzetek'] ?? null)) {
            Log::error('advisor.report.empty', [
                'model' => $model->value,
                'usage' => $response->usage->toArray(),
                'text' => $response->text,
                'structured' => $data,
                ...$this->responseDiagnostics($response),
            ]);

            throw new RuntimeException(sprintf(
                'Model %s ni vrnil strukturirane analize (korakov: %d, zaključek: %s).',
                $model->value,
                $response->steps->count(),
                $response->steps->last()?->finishReason->value ?? 'neznano',
            ));
        }

        return $data;
    }

    /**
     * A compact per-step trace of what the model actually did, so an empty or
     * partial report can be diagnosed from the log alone.
     *
     * @return array{steps: int, finish_reason: string, text_length: int, step_trace: array<int, array{finish_reason: string, tools: array<int, string>, text_length: int}>}
     */
    private function responseDiagnostics(AgentResponse $response): array
    {
        return [
            'steps' => $response->steps->count(),
            'finish_reason' => $response->steps->last()?->finishReason->value ?? 'neznano',
            'text_length' => mb_strlen($response->text),
            'step_trace' => $response->steps
                ->map(fn (Step $step): array => [
                    'finish_reason' => $step->finishReason->value,
                    'tools' => array_map(fn (ToolCall $call): string => $call->name, $step->toolCalls),
                    'text_length' => mb_strlen($step->text),
                ])
                ->all(),
        ];
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
