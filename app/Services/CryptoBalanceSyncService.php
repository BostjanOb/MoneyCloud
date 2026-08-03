<?php

namespace App\Services;

use App\Contracts\CryptoExchangeClient;
use App\Enums\BalanceSyncProvider;
use App\Enums\InvestmentSymbolType;
use App\Models\CryptoBalance;
use App\Models\InvestmentProvider;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CryptoBalanceSyncService
{
    public function __construct(
        private BinanceService $binanceService,
        private RevolutXService $revolutXService,
    ) {}

    /**
     * @return array{
     *     updated_count: int,
     *     skipped_count: int
     * }
     */
    public function syncProvider(InvestmentProvider $provider): array
    {
        $syncProvider = $provider->balanceSyncProvider();

        if ($syncProvider === null || ! $provider->supportsCrypto()) {
            throw new InvalidArgumentException('Ponudnik nima konfigurirane sinhronizacije kripto stanj.');
        }

        $client = match ($syncProvider) {
            BalanceSyncProvider::Binance => $this->binanceService,
            BalanceSyncProvider::RevolutX => $this->revolutXService,
        };

        if (! $client->isConfigured()) {
            throw new InvalidArgumentException("Sinhronizacija za {$syncProvider->label()} ni konfigurirana.");
        }

        return $this->syncBalances($provider, $client);
    }

    /**
     * Write the exchange overview onto the locally tracked crypto balances.
     *
     * Only balances that already exist for the provider are updated; assets
     * missing from the overview keep their stored quantity.
     *
     * @return array{
     *     updated_count: int,
     *     skipped_count: int
     * }
     */
    private function syncBalances(InvestmentProvider $provider, CryptoExchangeClient $client): array
    {
        $overview = collect($client->getBalanceOverview())
            ->mapWithKeys(fn (float|int $quantity, string $symbol): array => [
                strtoupper($symbol) => $this->formatQuantity($quantity),
            ])
            ->all();

        return DB::transaction(function () use ($provider, $overview): array {
            $balances = $provider->cryptoBalances()
                ->with('symbol')
                ->lockForUpdate()
                ->get()
                ->filter(fn (CryptoBalance $balance): bool => $balance->symbol->type === InvestmentSymbolType::CRYPTO)
                ->values();

            $updatedCount = 0;
            $skippedCount = 0;

            foreach ($balances as $balance) {
                $symbol = strtoupper($balance->symbol->symbol);

                if (! array_key_exists($symbol, $overview)) {
                    $skippedCount++;

                    continue;
                }

                $quantity = $overview[$symbol];

                if ($balance->manual_quantity !== $quantity) {
                    $balance->update([
                        'manual_quantity' => $quantity,
                    ]);
                }

                $updatedCount++;
            }

            return [
                'updated_count' => $updatedCount,
                'skipped_count' => $skippedCount,
            ];
        });
    }

    private function formatQuantity(float|int $quantity): string
    {
        return number_format((float) $quantity, 8, '.', '');
    }
}
