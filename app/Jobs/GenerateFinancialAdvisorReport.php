<?php

namespace App\Jobs;

use App\Enums\AdvisorModel;
use App\Services\FinancialAdvisorReportService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class GenerateFinancialAdvisorReport implements ShouldQueue
{
    use Queueable;

    /**
     * The number of seconds the job may run before timing out. Must stay well
     * above the agent's own request timeout so a slow generation is cut off by
     * the agent, not by the queue worker.
     */
    public int $timeout = 1800;

    public function __construct(
        public AdvisorModel $model = AdvisorModel::ClaudeSonnet46,
    ) {}

    public function handle(FinancialAdvisorReportService $reports): void
    {
        $reports->generate($this->model);
    }
}
