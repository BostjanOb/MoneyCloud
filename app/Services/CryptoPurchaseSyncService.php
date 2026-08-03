<?php

namespace App\Services;

use App\Contracts\CryptoTradeHistoryClient;
use App\Enums\BalanceSyncProvider;
use App\Enums\InvestmentSymbolType;
use App\Enums\InvestmentTransactionType;
use App\Models\InvestmentProvider;
use App\Models\InvestmentPurchase;
use App\Models\InvestmentSymbol;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CryptoPurchaseSyncService
{
    /**
     * Only trades settled in this currency are imported, matching the CSV importer.
     */
    private const QUOTE_CURRENCY = 'EUR';

    public function __construct(private RevolutXService $revolutXService) {}

    /**
     * Import the exchange trade history into `investment_purchases`.
     *
     * Crypto balances are left alone — those are synced straight from the
     * exchange by CryptoBalanceSyncService.
     *
     * @return array{
     *     created_count: int,
     *     skipped_duplicate: int,
     *     skipped_currency: int,
     *     skipped_symbols: list<string>
     * }
     */
    public function syncProvider(InvestmentProvider $provider, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $syncProvider = $provider->balanceSyncProvider();

        if ($syncProvider === null || ! $provider->supportsCrypto()) {
            throw new InvalidArgumentException('Ponudnik nima konfigurirane sinhronizacije transakcij.');
        }

        $client = match ($syncProvider) {
            BalanceSyncProvider::Binance => null,
            BalanceSyncProvider::RevolutX => $this->revolutXService,
        };

        if (! $client instanceof CryptoTradeHistoryClient) {
            throw new InvalidArgumentException("{$syncProvider->label()} ne podpira sinhronizacije transakcij.");
        }

        if (! $client->isConfigured()) {
            throw new InvalidArgumentException("Sinhronizacija za {$syncProvider->label()} ni konfigurirana.");
        }

        return $this->storeOrders($provider, $client->getFilledOrders($from, $to));
    }

    /**
     * @param  list<array<string, string>>  $orders
     * @return array{
     *     created_count: int,
     *     skipped_duplicate: int,
     *     skipped_currency: int,
     *     skipped_symbols: list<string>
     * }
     */
    private function storeOrders(InvestmentProvider $provider, array $orders): array
    {
        $symbols = InvestmentSymbol::where('type', InvestmentSymbolType::CRYPTO->value)
            ->get()
            ->keyBy('symbol');

        return DB::transaction(function () use ($provider, $orders, $symbols): array {
            $createdCount = 0;
            $skippedDuplicate = 0;
            $skippedCurrency = 0;
            $skippedSymbols = [];

            foreach ($orders as $order) {
                if ($order['quote_asset'] !== self::QUOTE_CURRENCY) {
                    $skippedCurrency++;

                    continue;
                }

                $symbol = $symbols->get($order['base_asset']);

                if (! $symbol instanceof InvestmentSymbol) {
                    if (! in_array($order['base_asset'], $skippedSymbols, true)) {
                        $skippedSymbols[] = $order['base_asset'];
                    }

                    continue;
                }

                $alreadyImported = InvestmentPurchase::where('investment_provider_id', $provider->id)
                    ->where('external_id', $order['external_id'])
                    ->exists();

                if ($alreadyImported) {
                    $skippedDuplicate++;

                    continue;
                }

                InvestmentPurchase::create([
                    'investment_provider_id' => $provider->id,
                    'investment_symbol_id' => $symbol->id,
                    'external_id' => $order['external_id'],
                    'purchased_at' => $order['executed_at'],
                    'transaction_type' => $this->transactionType($order['side'])->value,
                    'quantity' => $order['quantity'],
                    'price_per_unit' => $order['price_per_unit'],
                    'fee' => $order['fee'],
                ]);

                $createdCount++;
            }

            return [
                'created_count' => $createdCount,
                'skipped_duplicate' => $skippedDuplicate,
                'skipped_currency' => $skippedCurrency,
                'skipped_symbols' => $skippedSymbols,
            ];
        });
    }

    private function transactionType(string $side): InvestmentTransactionType
    {
        return InvestmentTransactionType::tryFrom($side) ?? InvestmentTransactionType::Buy;
    }
}
