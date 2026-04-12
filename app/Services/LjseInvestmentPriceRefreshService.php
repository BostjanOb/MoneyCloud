<?php

namespace App\Services;

use App\Contracts\InvestmentPriceRefreshService;
use App\Enums\InvestmentPriceSource;
use App\Enums\InvestmentSymbolType;
use App\Models\InvestmentSymbol;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class LjseInvestmentPriceRefreshService implements InvestmentPriceRefreshService
{
    private const DEFAULT_ERROR_MESSAGE = 'Osveževanje cen iz LJSE ni uspelo.';

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

        $response = $this->fetchPriceList();
        $rowsBySymbol = $this->mapRowsBySymbol($response->json('priceList'));
        $marketDataDate = $response->json('market_data_date');
        $updatedCount = 0;
        $failedSymbols = [];

        foreach ($symbols as $symbol) {
            $row = $rowsBySymbol->get((string) $symbol->external_source_id);

            if (! is_array($row) || ! is_numeric($row['last_price_n'] ?? null) || (float) $row['last_price_n'] <= 0) {
                $failedSymbols[] = $symbol->symbol;

                continue;
            }

            $symbol->forceFill([
                'current_price' => $this->resolveCurrentPrice($symbol, $row),
                'price_source' => InvestmentPriceSource::LJSE->value,
                'price_synced_at' => $this->resolveSyncedAt($row, $marketDataDate),
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
            ->where('price_source', InvestmentPriceSource::LJSE->value)
            ->whereIn('type', [
                InvestmentSymbolType::STOCK->value,
                InvestmentSymbolType::BOND->value,
            ])
            ->whereNotNull('external_source_id')
            ->orderBy('symbol')
            ->get();
    }

    private function fetchPriceList(): Response
    {
        try {
            $response = Http::acceptJson()
                ->connectTimeout(5)
                ->timeout(10)
                ->retry([200, 500, 1000], throw: false)
                ->get('https://ljse.si/json/TradingPriceList', [
                    'lng' => 'si',
                    'market_segment_ids' => 'A,D',
                    'type' => 'EQTY,DEBT',
                    'model' => 'CT',
                ]);
        } catch (ConnectionException $exception) {
            throw new RuntimeException('Povezava do LJSE ni uspela.', previous: $exception);
        }

        if ($response->failed()) {
            throw new RuntimeException($this->resolveErrorMessage($response));
        }

        return $response;
    }

    /**
     * @return Collection<string, array<string, mixed>>
     */
    private function mapRowsBySymbol(mixed $priceList): Collection
    {
        if (! is_array($priceList)) {
            return collect();
        }

        return collect($priceList)
            ->flatMap(function (mixed $segment): array {
                if (! is_array($segment)) {
                    return [];
                }

                $rows = $segment['tradingPriceList']['rows'] ?? [];

                return is_array($rows) ? $rows : [];
            })
            ->filter(fn (mixed $row): bool => is_array($row) && is_string($row['symbol'] ?? null))
            ->mapWithKeys(fn (array $row): array => [mb_strtoupper((string) $row['symbol']) => $row]);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function resolveCurrentPrice(InvestmentSymbol $symbol, array $row): float
    {
        $lastPrice = (float) $row['last_price_n'];

        if ($symbol->type === InvestmentSymbolType::BOND) {
            return round(1000 * ($lastPrice / 100), 2);
        }

        return round($lastPrice, 2);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function resolveSyncedAt(array $row, mixed $marketDataDate): CarbonImmutable
    {
        $timestamp = $row['date'] ?? $marketDataDate;

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
        $message = trim((string) $response->json('Message', ''));

        if ($message !== '') {
            return $message;
        }

        return self::DEFAULT_ERROR_MESSAGE;
    }
}
