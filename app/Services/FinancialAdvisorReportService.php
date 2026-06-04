<?php

namespace App\Services;

use App\Ai\Agents\FinancialAnalyst;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

/**
 * Generates and caches the periodic structured analysis produced by the
 * {@see FinancialAnalyst} agent.
 */
class FinancialAdvisorReportService
{
    public const CACHE_KEY = 'financial_advisor.report';

    public const GENERATING_KEY = 'financial_advisor.generating';

    public function __construct(
        private readonly FinancialContextService $context,
    ) {}

    /**
     * Generate a fresh analysis, cache it, and return it.
     *
     * @return array{generated_at: string, report: array<string, mixed>}
     */
    public function generate(): array
    {
        try {
            $response = (new FinancialAnalyst)->prompt($this->buildPrompt());
            
            $payload = [
                'generated_at' => CarbonImmutable::now('Europe/Ljubljana')->toIso8601String(),
                'report' => $response->toArray(),
            ];

            Cache::forever(self::CACHE_KEY, $payload);

            return $payload;
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
    public function cached(): ?array
    {
        return Cache::get(self::CACHE_KEY);
    }

    public function clear(): void
    {
        Cache::forget(self::CACHE_KEY);
        Cache::forget(self::GENERATING_KEY);
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
