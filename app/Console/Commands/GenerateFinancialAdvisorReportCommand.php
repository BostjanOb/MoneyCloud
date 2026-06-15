<?php

namespace App\Console\Commands;

use App\Enums\AdvisorProvider;
use App\Jobs\GenerateFinancialAdvisorReport;
use App\Services\FinancialAdvisorReportService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('advisor:generate-report {--sync : Generiraj sinhrono namesto v ozadju} {--provider=anthropic : AI provider (anthropic|openai)}')]
#[Description('Generira strukturirano finančno analizo gospodinjstva.')]
class GenerateFinancialAdvisorReportCommand extends Command
{
    public function handle(FinancialAdvisorReportService $reports): int
    {
        $provider = AdvisorProvider::tryFrom($this->option('provider'));

        if (! $provider) {
            $this->error('Neveljaven provider. Uporabi anthropic ali openai.');

            return self::FAILURE;
        }

        if ($this->option('sync')) {
            $reports->generate($provider);

            $this->info("Finančna analiza ({$provider->label()}) je generirana in shranjena.");

            return self::SUCCESS;
        }

        GenerateFinancialAdvisorReport::dispatch($provider);

        $this->info("Generiranje finančne analize ({$provider->label()}) je dodano v vrsto.");

        return self::SUCCESS;
    }
}
