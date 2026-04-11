<?php

namespace App\Console\Commands;

use App\Contracts\InvestmentPriceRefreshService;
use App\Services\MonthlyPortfolioSnapshotService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('statistics:capture-monthly-portfolio-snapshot {--month= : Mesec v obliki YYYY-MM ali YYYY-MM-DD}')]
#[Description('Shrani mesečni povzetek portfelja.')]
class CaptureMonthlyPortfolioSnapshotCommand extends Command
{
    public function handle(
        InvestmentPriceRefreshService $investmentPriceRefreshService,
        MonthlyPortfolioSnapshotService $monthlyPortfolioSnapshotService,
    ): int {
        $monthDate = $this->resolveMonthOption();

        if ($monthDate === false) {
            return self::FAILURE;
        }

        $investmentPriceRefreshService->refresh();

        $snapshot = $monthlyPortfolioSnapshotService->capture($monthDate);

        $this->info(sprintf(
            'Mesečni povzetek za %s je shranjen.',
            $snapshot->month_date?->format('m.Y'),
        ));

        return self::SUCCESS;
    }

    private function resolveMonthOption(): CarbonImmutable|false|null
    {
        $month = $this->option('month');

        if (! is_string($month) || $month === '') {
            return null;
        }

        if (preg_match('/^\d{4}-\d{2}$/', $month) === 1) {
            $month .= '-01';
        }

        try {
            return CarbonImmutable::parse($month)->startOfMonth();
        } catch (Throwable) {
            $this->error('Možnost --month mora biti v obliki YYYY-MM ali YYYY-MM-DD.');

            return false;
        }
    }
}
