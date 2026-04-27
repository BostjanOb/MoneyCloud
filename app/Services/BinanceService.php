<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Http;

class BinanceService
{
    protected string $apiKey;

    protected string $apiSecret;

    protected string $baseUrl;

    protected string $fapiUrl;

    protected string $sapiUrl;

    protected int $timeOffset = 0;

    public function __construct()
    {
        $this->apiKey = config('services.binance.api_key');
        $this->apiSecret = config('services.binance.api_secret');
        $this->baseUrl = config('services.binance.base_url');
        $this->fapiUrl = config('services.binance.fapi_url');
        $this->sapiUrl = config('services.binance.sapi_url');
    }

    /**
     * Sync server time offset (call once before signed requests if needed)
     */
    public function syncServerTime(): void
    {
        $response = Http::get($this->baseUrl.'v3/time');
        if ($response->successful()) {
            $this->timeOffset = $response->json('serverTime') - intval(microtime(true) * 1000);
        }
    }

    /**
     * Build the HMAC-SHA256 signature
     */
    protected function buildSignedQuery(array $params): string
    {
        $query = http_build_query($params, '', '&');
        $signature = hash_hmac('sha256', $query, $this->apiSecret);

        return $query.'&signature='.$signature;
    }

    /**
     * Get SPOT balances
     */
    public function getSpotBalances(): array
    {
        $params = [
            'timestamp' => intval(microtime(true) * 1000) + $this->timeOffset,
            'recvWindow' => 5000,
        ];

        $signedQuery = $this->buildSignedQuery($params);

        $response = Http::withHeaders([
            'X-MBX-APIKEY' => $this->apiKey,
        ])->get($this->baseUrl.'v3/account?'.$signedQuery);

        if (! $response->successful()) {
            throw new Exception('Binance API error: '.$response->body());
        }

        return $this->formatSpotBalances($response->json());
    }

    /**
     * Format spot balances
     */
    protected function formatSpotBalances(array $response): array
    {
        $balances = [];

        if (empty($response['balances'])) {
            return $balances;
        }

        foreach ($response['balances'] as $item) {
            $available = (float) $item['free'];
            $onOrder = (float) $item['locked'];
            $total = $available + $onOrder;

            $balances[$item['asset']] = [
                'available' => $available,
                'onOrder' => $onOrder,
                'total' => $total,
                'info' => $item,
            ];
        }

        return $balances;
    }

    /**
     * Get Simple Earn flexible balances
     */
    public function getFlexibleSimpleEarnBalances(int $size = 50): array
    {
        $params = [
            'size' => $size,
            'timestamp' => intval(microtime(true) * 1000) + $this->timeOffset,
            'recvWindow' => 5000,
        ];

        $signedQuery = $this->buildSignedQuery($params);

        $response = Http::withHeaders([
            'X-MBX-APIKEY' => $this->apiKey,
        ])->get($this->sapiUrl.'v1/simple-earn/flexible/position?'.$signedQuery);

        if (! $response->successful()) {
            throw new Exception('Binance flexible Simple Earn error: '.$response->body());
        }

        return $this->formatFlexibleSimpleEarnBalances($response->json());
    }

    /**
     * Get combined overview of spot and flexible Simple Earn balances
     *
     * @return array<string, float>
     */
    public function getBalanceOverview(): array
    {
        $overview = [];

        foreach ($this->getSpotBalances() as $asset => $balance) {
            $overview[$asset] = round((float) ($balance['total'] ?? 0), 8);
        }

        foreach ($this->getFlexibleSimpleEarnBalances() as $asset => $balance) {
            $overview[$asset] = round(
                ($overview[$asset] ?? 0.0) + (float) ($balance['total'] ?? 0),
                8,
            );
        }

        return array_filter($overview, fn (float $total): bool => $total > 0);
    }

    /**
     * Format balances from the Simple Earn flexible positions response
     */
    protected function formatFlexibleSimpleEarnBalances(array $data): array
    {
        $rows = $data['rows'] ?? [];

        if (empty($rows)) {
            return [];
        }

        $balances = [];

        foreach ($rows as $item) {
            $asset = $item['asset'] ?? null;
            $total = (float) ($item['totalAmount'] ?? 0);

            if (! is_string($asset) || $asset === '' || $total <= 0) {
                continue;
            }

            if (! isset($balances[$asset])) {
                $balances[$asset] = [
                    'available' => 0.0,
                    'onOrder' => 0.0,
                    'total' => 0.0,
                    'positions' => [],
                    'info' => null,
                ];
            }

            $balances[$asset]['available'] += $total;
            $balances[$asset]['total'] += $total;
            $balances[$asset]['positions'][] = $item;
            $balances[$asset]['info'] = $item;
        }

        return $balances;
    }
}
