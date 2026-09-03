<?php

namespace App\Console\Commands;

use App\Enums\AdvisorModel;
use App\Jobs\GenerateFinancialAdvisorReport;
use App\Services\FinancialAdvisorReportService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('advisor:generate-report {--sync : Generiraj sinhrono namesto v ozadju} {--model= : AI model; privzeto claude-sonnet-5}')]
#[Description('Generira strukturirano finančno analizo gospodinjstva.')]
class GenerateFinancialAdvisorReportCommand extends Command
{
    public function handle(FinancialAdvisorReportService $reports): int
    {
        $model = AdvisorModel::tryFrom($this->option('model') ?? AdvisorModel::ClaudeSonnet5->value);

        if (! $model) {
            $this->error('Neveljaven model. Uporabi enega izmed: '.implode(', ', array_column(AdvisorModel::cases(), 'value')).'.');

            return self::FAILURE;
        }

        if ($this->option('sync')) {
            $reports->generate($model);

            $this->info("Finančna analiza ({$model->label()}) je generirana in shranjena.");

            return self::SUCCESS;
        }

        GenerateFinancialAdvisorReport::dispatch($model);

        $this->info("Generiranje finančne analize ({$model->label()}) je dodano v vrsto.");

        return self::SUCCESS;
    }
}
