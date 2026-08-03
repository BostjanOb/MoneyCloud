<?php

namespace App\Console\Commands;

use App\Models\InvestmentProvider;
use App\Services\CryptoPurchaseSyncService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('investments:sync-crypto-purchases {--days=14 : Število dni nazaj za sinhronizacijo}')]
#[Description('Sinhroniziraj kripto DCA transakcije za ponudnike s podprto sinhronizacijo.')]
class SyncCryptoPurchasesCommand extends Command
{
    public function handle(CryptoPurchaseSyncService $cryptoPurchaseSyncService): int
    {
        $providers = InvestmentProvider::whereNotNull('balance_sync_provider')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->filter(fn (InvestmentProvider $provider): bool => $provider->canSyncPurchases());

        if ($providers->isEmpty()) {
            $this->info('Ni ponudnikov s podprto sinhronizacijo transakcij.');

            return self::SUCCESS;
        }

        $to = CarbonImmutable::now();
        $from = $to->subDays(max(1, (int) $this->option('days')));

        $createdCount = 0;
        $skippedDuplicate = 0;
        $missingSymbols = [];
        $failedProviders = [];

        foreach ($providers as $provider) {
            try {
                $result = $cryptoPurchaseSyncService->syncProvider($provider, $from, $to);

                $createdCount += $result['created_count'];
                $skippedDuplicate += $result['skipped_duplicate'];
                $missingSymbols = array_unique([...$missingSymbols, ...$result['skipped_symbols']]);

                $this->info($this->providerStatusMessage($provider, $result));
            } catch (Throwable $exception) {
                $failedProviders[] = $provider->name;
                $this->error(sprintf('%s: %s', $provider->name, $exception->getMessage()));
            }
        }

        $this->info(sprintf(
            'Skupaj uvoženih: %d. Skupaj preskočenih duplikatov: %d.',
            $createdCount,
            $skippedDuplicate,
        ));

        if ($missingSymbols !== []) {
            $this->warn('Manjkajoči simboli: '.implode(', ', $missingSymbols));
        }

        if ($failedProviders === []) {
            return self::SUCCESS;
        }

        $this->warn('Neuspešni ponudniki: '.implode(', ', $failedProviders));

        return self::FAILURE;
    }

    /**
     * @param  array{
     *     created_count: int,
     *     skipped_duplicate: int,
     *     skipped_currency: int,
     *     skipped_symbols: list<string>
     * }  $result
     */
    private function providerStatusMessage(InvestmentProvider $provider, array $result): string
    {
        if ($result['created_count'] === 0 && $result['skipped_duplicate'] === 0) {
            return sprintf('%s: ni novih transakcij.', $provider->name);
        }

        return sprintf(
            '%s: uvoženih %d transakcij, preskočenih duplikatov %d.',
            $provider->name,
            $result['created_count'],
            $result['skipped_duplicate'],
        );
    }
}
