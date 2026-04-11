<?php

namespace App\Services;

use App\Contracts\InvestmentPriceRefreshService;
use App\Enums\InvestmentSymbolType;
use App\Models\InvestmentSymbol;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class CoinMarketCapInvestmentPriceRefreshService implements InvestmentPriceRefreshService
{
    private const DEFAULT_ERROR_MESSAGE = 'Osveževanje cen iz CoinMarketCap ni uspelo.';

    /**
     * @return array{
     *     updated_count: int,
     *     skipped_count: int,
     *     failed_symbols: list<string>
     * }
     */
    public function refresh(): array
    {
        $symbols = $this->symbolsToRefresh();

        if ($symbols->isEmpty()) {
            return [
                'updated_count' => 0,
                'skipped_count' => 0,
                'failed_symbols' => [],
            ];
        }

        $quotesById = $this->fetchQuotes($symbols);
        $updatedCount = 0;
        $failedSymbols = [];

        foreach ($symbols as $symbol) {
            $quotePayload = $quotesById->get($symbol->coinmarketcap_id);

            if (! is_array($quotePayload)) {
                $failedSymbols[] = $symbol->symbol;

                continue;
            }

            $eurQuote = collect($quotePayload['quote'] ?? [])
                ->first(fn (mixed $quote): bool => is_array($quote) && mb_strtoupper((string) ($quote['symbol'] ?? '')) === 'EUR');

            if (! is_array($eurQuote) || ! is_numeric($eurQuote['price'] ?? null)) {
                $failedSymbols[] = $symbol->symbol;

                continue;
            }

            $symbol->forceFill([
                'current_price' => round((float) $eurQuote['price'], 2),
                'price_source' => 'coinmarketcap',
                'price_synced_at' => $this->resolveSyncedAt($quotePayload, $eurQuote),
            ])->save();

            $updatedCount++;
        }

        return [
            'updated_count' => $updatedCount,
            'skipped_count' => count($failedSymbols),
            'failed_symbols' => $failedSymbols,
        ];
    }

    /**
     * @return Collection<int, InvestmentSymbol>
     */
    private function symbolsToRefresh(): Collection
    {
        return InvestmentSymbol::query()
            ->where('type', InvestmentSymbolType::CRYPTO->value)
            ->whereNotNull('coinmarketcap_id')
            ->orderBy('symbol')
            ->get();
    }

    /**
     * @param  Collection<int, InvestmentSymbol>  $symbols
     * @return Collection<int, array<string, mixed>>
     */
    private function fetchQuotes(Collection $symbols): Collection
    {
        $apiKey = config('services.coinmarketcap.key');

        if (! is_string($apiKey) || $apiKey === '') {
            throw new RuntimeException('CoinMarketCap API ključ ni nastavljen.');
        }

        $baseUrl = rtrim((string) config('services.coinmarketcap.base_url'), '/');

        try {
            $response = Http::acceptJson()
                ->withHeaders([
                    'X-CMC_PRO_API_KEY' => $apiKey,
                ])
                ->connectTimeout(5)
                ->timeout(10)
                ->retry([200, 500, 1000], throw: false)
                ->get($baseUrl.'/v3/cryptocurrency/quotes/latest', [
                    'id' => $symbols->pluck('coinmarketcap_id')->implode(','),
                    'convert' => 'EUR',
                ]);
        } catch (ConnectionException $exception) {
            throw new RuntimeException('Povezava do CoinMarketCap ni uspela.', previous: $exception);
        }

        if ($response->failed()) {
            throw new RuntimeException($this->resolveErrorMessage($response));
        }

        $errorCode = (string) $response->json('status.error_code', '0');

        if ($errorCode !== '0') {
            throw new RuntimeException($this->resolveErrorMessage($response));
        }

        $data = $response->json('data');

        if (! is_array($data)) {
            return collect();
        }

        return collect($data)
            ->filter(fn (mixed $item): bool => is_array($item) && isset($item['id']))
            ->mapWithKeys(fn (array $item): array => [(int) $item['id'] => $item]);
    }

    /**
     * @param  array<string, mixed>  $quotePayload
     * @param  array<string, mixed>  $eurQuote
     */
    private function resolveSyncedAt(array $quotePayload, array $eurQuote): CarbonImmutable
    {
        $timestamp = $eurQuote['last_updated'] ?? $quotePayload['last_updated'] ?? null;

        if (! is_string($timestamp) || $timestamp === '') {
            return CarbonImmutable::now();
        }

        try {
            return CarbonImmutable::parse($timestamp);
        } catch (Throwable) {
            return CarbonImmutable::now();
        }
    }

    private function resolveErrorMessage(Response $response): string
    {
        $errorMessage = trim((string) $response->json('status.error_message', ''));

        if ($errorMessage !== '') {
            return $errorMessage;
        }

        return self::DEFAULT_ERROR_MESSAGE;
    }
}
