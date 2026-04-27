<?php

namespace App\Services;

use App\Enums\BalanceSyncProvider;
use App\Enums\InvestmentSymbolType;
use App\Models\CryptoBalance;
use App\Models\InvestmentProvider;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CryptoBalanceSyncService
{
    public function __construct(private BinanceService $binanceService) {}

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

        return match ($syncProvider) {
            BalanceSyncProvider::Binance => $this->syncBinanceBalances($provider),
        };
    }

    /**
     * @return array{
     *     updated_count: int,
     *     skipped_count: int
     * }
     */
    private function syncBinanceBalances(InvestmentProvider $provider): array
    {
        $this->binanceService->syncServerTime();

        $overview = collect($this->binanceService->getBalanceOverview())
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
