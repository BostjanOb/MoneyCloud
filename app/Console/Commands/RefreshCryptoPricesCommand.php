<?php

namespace App\Console\Commands;

use App\Contracts\InvestmentPriceRefreshService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('investments:refresh-crypto-prices')]
#[Description('Osveži cene kripto simbolov iz CoinMarketCap.')]
class RefreshCryptoPricesCommand extends Command
{
    public function handle(InvestmentPriceRefreshService $investmentPriceRefreshService): int
    {
        try {
            $result = $investmentPriceRefreshService->refresh();
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $totalHandled = $result['updated_count'] + $result['skipped_count'];

        if ($totalHandled === 0) {
            $this->info('Ni kripto simbolov z nastavljenim CoinMarketCap ID-jem za osvežitev.');

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
