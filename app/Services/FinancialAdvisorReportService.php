<?php

namespace App\Services;

use App\Ai\Agents\FinancialAnalyst;
use App\Models\FinancialAdvisorReport;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

/**
 * Generates and persists the periodic structured analysis produced by the
 * {@see FinancialAnalyst} agent.
 */
class FinancialAdvisorReportService
{
    public const GENERATING_KEY = 'financial_advisor.generating';

    public function __construct(
        private readonly FinancialContextService $context,
    ) {}

    /**
     * Generate a fresh analysis, persist it, and return it.
     *
     * @return array{generated_at: string, report: array<string, mixed>}
     */
    public function generate(): array
    {
        try {
            $response = (new FinancialAnalyst)->prompt($this->buildPrompt());

            $report = FinancialAdvisorReport::create([
                'generated_at' => CarbonImmutable::now('Europe/Ljubljana'),
                'report' => $response->toArray(),
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
     * @return array{generated_at: string, report: array<string, mixed>}|null
     */
    public function latest(): ?array
    {
        $report = FinancialAdvisorReport::latestFirst()->first();

        return $report ? $this->toPayload($report) : null;
    }

    public function clear(): void
    {
        Cache::forget(self::GENERATING_KEY);
    }

    /**
     * Shape a stored report into the payload the frontend expects.
     *
     * @return array{generated_at: string, report: array<string, mixed>}
     */
    private function toPayload(FinancialAdvisorReport $report): array
    {
        return [
            'generated_at' => $report->generated_at->toIso8601String(),
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
}
