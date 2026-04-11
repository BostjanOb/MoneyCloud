<?php

namespace App\Console\Commands;

use App\Services\YfApiInvestmentPriceRefreshService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('investments:refresh-yfapi-prices')]
#[Description('Osveži cene delnic in ETF-jev iz YF API.')]
class RefreshYfApiPricesCommand extends Command
{
    public function handle(YfApiInvestmentPriceRefreshService $service): int
    {
        try {
            $result = $service->refresh();
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $totalHandled = $result['updated_count'] + $result['skipped_count'];

        if ($totalHandled === 0) {
            $this->info('Ni simbolov z virom cene "yfapi" za osvežitev.');

            return self::SUCCESS;
        }

        $this->info(sprintf(
            'Osveženih: %d. Preskočenih: %d.',
            $result['updated_count'],
            $result['skipped_count'],
        ));

        if ($result['failed_symbols'] !== []) {
            $this->warn('Neuspešni simboli: '.implode(', ', $result['failed_symbols']));
        }

        return self::SUCCESS;
    }
}
