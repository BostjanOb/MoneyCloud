<?php

namespace App\Jobs;

use App\Enums\AdvisorProvider;
use App\Services\FinancialAdvisorReportService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class GenerateFinancialAdvisorReport implements ShouldQueue
{
    use Queueable;

    /**
     * The number of seconds the job may run before timing out.
     */
    public int $timeout = 300;

    public function __construct(
        public AdvisorProvider $provider = AdvisorProvider::Anthropic,
    ) {}

    public function handle(FinancialAdvisorReportService $reports): void
    {
        $reports->generate($this->provider);
    }
}
