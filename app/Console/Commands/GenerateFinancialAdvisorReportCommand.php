<?php

namespace App\Console\Commands;

use App\Jobs\GenerateFinancialAdvisorReport;
use App\Services\FinancialAdvisorReportService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('advisor:generate-report {--sync : Generiraj sinhrono namesto v ozadju}')]
#[Description('Generira strukturirano finančno analizo gospodinjstva.')]
class GenerateFinancialAdvisorReportCommand extends Command
{
    public function handle(FinancialAdvisorReportService $reports): int
    {
        if ($this->option('sync')) {
            $reports->generate();

            $this->info('Finančna analiza je generirana in shranjena.');

            return self::SUCCESS;
        }

        GenerateFinancialAdvisorReport::dispatch();

        $this->info('Generiranje finančne analize je dodano v vrsto.');

        return self::SUCCESS;
    }
}
