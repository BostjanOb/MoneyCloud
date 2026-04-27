<?php

namespace App\Console\Commands;

use App\Models\InvestmentProvider;
use App\Services\CryptoBalanceSyncService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('investments:sync-crypto-balances')]
#[Description('Sinhroniziraj kripto stanja za ponudnike z omogočeno sinhronizacijo.')]
class SyncCryptoBalancesCommand extends Command
{
    public function handle(CryptoBalanceSyncService $cryptoBalanceSyncService): int
    {
        $providers = InvestmentProvider::whereNotNull('balance_sync_provider')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        if ($providers->isEmpty()) {
            $this->info('Ni ponudnikov s konfigurirano sinhronizacijo stanj.');

            return self::SUCCESS;
        }

        $updatedCount = 0;
        $skippedCount = 0;
        $failedProviders = [];

        foreach ($providers as $provider) {
            try {
                $result = $cryptoBalanceSyncService->syncProvider($provider);

                $updatedCount += $result['updated_count'];
                $skippedCount += $result['skipped_count'];

                $this->info($this->providerStatusMessage($provider, $result));
            } catch (Throwable $exception) {
                $failedProviders[] = $provider->name;
                $this->error(sprintf('%s: %s', $provider->name, $exception->getMessage()));
            }
        }

        $this->info(sprintf(
            'Skupaj sinhroniziranih: %d. Skupaj preskočenih: %d.',
            $updatedCount,
            $skippedCount,
        ));

        if ($failedProviders === []) {
            return self::SUCCESS;
        }

        $this->warn('Neuspešni ponudniki: '.implode(', ', $failedProviders));

        return self::FAILURE;
    }

    /**
     * @param  array{
     *     updated_count: int,
     *     skipped_count: int
     * }  $result
     */
    private function providerStatusMessage(InvestmentProvider $provider, array $result): string
    {
        $totalHandled = $result['updated_count'] + $result['skipped_count'];

        if ($totalHandled === 0) {
            return sprintf('%s: ni konfiguriranih stanj za sinhronizacijo.', $provider->name);
        }

        return sprintf(
            '%s: sinhroniziranih %d stanj, preskočenih %d.',
            $provider->name,
            $result['updated_count'],
            $result['skipped_count'],
        );
    }
}
