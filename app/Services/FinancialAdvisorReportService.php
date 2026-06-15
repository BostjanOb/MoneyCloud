<?php

namespace App\Services;

use App\Ai\Agents\FinancialAnalyst;
use App\Enums\AdvisorProvider;
use App\Models\FinancialAdvisorReport;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Context;

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
     * @return array{id: int, generated_at: string, provider: string|null, report: array<string, mixed>}
     */
    public function generate(AdvisorProvider $provider = AdvisorProvider::Anthropic): array
    {
        try {
            $response = $this->actualBudget->isConfigured()
                ? $this->generateWithActualBudgetContext($provider)
                : (new FinancialAnalyst)->prompt($this->buildPrompt(), provider: $provider->promptTarget())->toArray();

            $report = FinancialAdvisorReport::create([
                'generated_at' => CarbonImmutable::now('Europe/Ljubljana'),
                'provider' => $provider,
                'report' => $response,
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
     * @return array{id: int, generated_at: string, provider: string|null, report: array<string, mixed>}|null
     */
    public function latest(): ?array
    {
        $report = FinancialAdvisorReport::latestFirst()->first();

        return $report ? $this->toPayload($report) : null;
    }

    /**
     * A specific report by id, falling back to the latest if it does not exist.
     *
     * @return array{id: int, generated_at: string, provider: string|null, report: array<string, mixed>}|null
     */
    public function find(int $id): ?array
    {
        $report = FinancialAdvisorReport::find($id);

        return $report ? $this->toPayload($report) : $this->latest();
    }

    /**
     * A lightweight list of every report for the history selector.
     *
     * @return array<int, array{id: int, generated_at: string, provider: string|null}>
     */
    public function history(): array
    {
        return FinancialAdvisorReport::latestFirst()
            ->get()
            ->map(fn (FinancialAdvisorReport $report): array => [
                'id' => $report->id,
                'generated_at' => $report->generated_at->toIso8601String(),
                'provider' => $report->provider?->value,
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
     * @return array{id: int, generated_at: string, provider: string|null, report: array<string, mixed>}
     */
    private function toPayload(FinancialAdvisorReport $report): array
    {
        return [
            'id' => $report->id,
            'generated_at' => $report->generated_at->toIso8601String(),
            'provider' => $report->provider?->value,
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
     * @return array<string, mixed>
     */
    private function generateWithActualBudgetContext(AdvisorProvider $provider): array
    {
        $actualBudgetContext = $this->actualBudget->reportContext();
        $response = Context::scope(
            fn () => (new FinancialAnalyst)->prompt($this->buildActualBudgetPrompt(), provider: $provider->promptTarget())->toArray(),
            hidden: [ActualBudgetContextService::REPORT_CONTEXT_KEY => $actualBudgetContext],
        );

        if ($actualBudgetContext['warnings'] ?? []) {
            $response['opozorila'] = array_values(array_unique([
                ...($response['opozorila'] ?? []),
                ...$actualBudgetContext['warnings'],
            ]));
        }

        return $response;
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
