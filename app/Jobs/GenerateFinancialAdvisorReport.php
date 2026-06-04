<?php

namespace App\Jobs;

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

    public function handle(FinancialAdvisorReportService $reports): void
    {
        $reports->generate();
    }
}
