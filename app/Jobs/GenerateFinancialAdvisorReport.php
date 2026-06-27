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
     * The number of seconds the job may run before timing out.
     */
    public int $timeout = 300;

    public function __construct(
        public AdvisorModel $model = AdvisorModel::ClaudeSonnet46,
    ) {}

    public function handle(FinancialAdvisorReportService $reports): void
    {
        $reports->generate($this->model);
    }
}
