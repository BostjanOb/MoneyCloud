<?php

namespace App\Services;

use App\Contracts\InvestmentPriceRefreshService;
use App\Models\InvestmentSymbol;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class YfApiInvestmentPriceRefreshService implements InvestmentPriceRefreshService
{
    private const BATCH_SIZE = 10;

    private const DEFAULT_ERROR_MESSAGE = 'Osveževanje cen iz YF API ni uspelo.';

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

        $updatedCount = 0;
        $failedSymbols = [];

        foreach ($symbols->chunk(self::BATCH_SIZE) as $batch) {
            $quotesBySymbol = $this->fetchQuotes($batch);

            foreach ($batch as $symbol) {
                $quote = $quotesBySymbol->get($symbol->yfapi_symbol);

                if (! is_array($quote) || ! is_numeric($quote['regularMarketPrice'] ?? null)) {
                    $failedSymbols[] = $symbol->symbol;

                    continue;
                }

                $symbol->forceFill([
                    'current_price' => round((float) $quote['regularMarketPrice'], 2),
                    'price_source' => 'yfapi',
                    'price_synced_at' => $this->resolveSyncedAt($quote),
                ])->save();

                $updatedCount++;
            }
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
            ->whereNotNull('yfapi_symbol')
            ->orderBy('symbol')
            ->get();
    }

    /**
     * @param  Collection<int, InvestmentSymbol>  $symbols
     * @return Collection<string, array<string, mixed>>
     */
    private function fetchQuotes(Collection $symbols): Collection
    {
        $apiKey = config('services.yfapi.key');

        if (! is_string($apiKey) || $apiKey === '') {
            throw new RuntimeException('YF API ključ ni nastavljen.');
        }

        $baseUrl = rtrim((string) config('services.yfapi.base_url'), '/');

        try {
            $response = Http::acceptJson()
                ->withHeaders([
                    'X-API-KEY' => $apiKey,
                ])
                ->connectTimeout(5)
                ->timeout(10)
                ->retry([200, 500, 1000], throw: false)
                ->get($baseUrl.'/v6/finance/quote', [
                    'symbols' => $symbols->pluck('yfapi_symbol')->implode(','),
                ]);
        } catch (ConnectionException $exception) {
            throw new RuntimeException('Povezava do YF API ni uspela.', previous: $exception);
        }

        if ($response->failed()) {
            throw new RuntimeException($this->resolveErrorMessage($response));
        }

        $results = $response->json('quoteResponse.result');

        if (! is_array($results)) {
            return collect();
        }

        return collect($results)
            ->filter(fn (mixed $item): bool => is_array($item) && isset($item['symbol']))
            ->mapWithKeys(fn (array $item): array => [(string) $item['symbol'] => $item]);
    }

    /**
     * @param  array<string, mixed>  $quote
     */
    private function resolveSyncedAt(array $quote): CarbonImmutable
    {
        $timestamp = $quote['regularMarketTime'] ?? null;

        if (is_int($timestamp) && $timestamp > 0) {
            try {
                return CarbonImmutable::createFromTimestamp($timestamp);
            } catch (Throwable) {
                return CarbonImmutable::now();
            }
        }

        return CarbonImmutable::now();
    }

    private function resolveErrorMessage(Response $response): string
    {
        $errorMessage = trim((string) $response->json('quoteResponse.error', ''));

        if ($errorMessage !== '') {
            return $errorMessage;
        }

        return self::DEFAULT_ERROR_MESSAGE;
    }
}
