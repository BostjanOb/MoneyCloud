<?php

namespace App\Services;

use App\Contracts\CryptoExchangeClient;
use App\Contracts\CryptoTradeHistoryClient;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class RevolutXService implements CryptoExchangeClient, CryptoTradeHistoryClient
{
    /**
     * Length of the PKCS#8 DER header that precedes the raw Ed25519 seed.
     */
    private const PKCS8_HEADER_LENGTH = 16;

    private const ED25519_SEED_LENGTH = 32;

    /**
     * The API rejects historical queries spanning more than 30 days.
     */
    private const MAX_WINDOW_DAYS = 29;

    private const MAX_PAGE_SIZE = 1900;

    /**
     * Safety net so a misbehaving cursor cannot loop forever.
     */
    private const MAX_PAGES_PER_WINDOW = 20;

    private const MAX_ATTEMPTS = 3;

    private const MIN_RETRY_MICROSECONDS = 500_000;

    private const MAX_RETRY_MICROSECONDS = 30_000_000;

    protected string $apiKey;

    protected string $privateKeyPath;

    protected string $baseUrl;

    private ?string $secretKey = null;

    public function __construct()
    {
        $this->apiKey = (string) config('services.revolutx.api_key');
        $this->privateKeyPath = (string) config('services.revolutx.private_key_path');
        $this->baseUrl = rtrim((string) config('services.revolutx.base_url'), '/');
    }

    public function isConfigured(): bool
    {
        return filled($this->apiKey)
            && filled($this->privateKeyPath)
            && is_readable($this->privateKeyPath);
    }

    /**
     * Total held quantity per asset, keyed by uppercase ticker.
     *
     * Includes fiat currencies and zero balances, so an asset sold off on the
     * exchange is written back as zero instead of keeping a stale quantity.
     *
     * @return array<string, float>
     */
    public function getBalanceOverview(): array
    {
        $overview = [];

        foreach ($this->get('/1.0/balances') as $entry) {
            $currency = $entry['currency'] ?? null;

            if (! is_string($currency) || $currency === '') {
                continue;
            }

            $overview[strtoupper($currency)] = round((float) ($entry['total'] ?? 0), 8);
        }

        return $overview;
    }

    /**
     * Filled orders executed in the given period.
     *
     * Historical queries are capped at 30 days per request and are metered by
     * the width of the requested range, so the period is walked in windows and
     * each window is paged through with the cursor the API hands back.
     *
     * @return list<array{
     *     external_id: string,
     *     base_asset: string,
     *     quote_asset: string,
     *     side: string,
     *     executed_at: string,
     *     quantity: string,
     *     price_per_unit: string,
     *     fee: string
     * }>
     */
    public function getFilledOrders(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $orders = [];

        foreach ($this->windows($from, $to) as [$windowStart, $windowEnd]) {
            foreach ($this->historicalOrders($windowStart, $windowEnd) as $order) {
                if (($order['status'] ?? null) !== 'filled' || (float) ($order['filled_quantity'] ?? 0) <= 0) {
                    continue;
                }

                $orders[] = $this->normalizeOrder($order, $this->orderDetails((string) $order['id']));
            }
        }

        return $orders;
    }

    /**
     * Split the period into consecutive windows the API will accept.
     *
     * @return list<array{0: CarbonImmutable, 1: CarbonImmutable}>
     */
    private function windows(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $windows = [];
        $windowStart = $from;

        while ($windowStart < $to) {
            $windowEnd = min($windowStart->addDays(self::MAX_WINDOW_DAYS), $to);
            $windows[] = [$windowStart, $windowEnd];
            $windowStart = $windowEnd;
        }

        return $windows;
    }

    /**
     * Page through every historical order within a single window.
     *
     * @return list<array<string, mixed>>
     */
    private function historicalOrders(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $orders = [];
        $cursor = '';
        $page = 0;

        do {
            $query = [
                'start_date' => $from->getTimestampMs(),
                'end_date' => $to->getTimestampMs(),
                'limit' => self::MAX_PAGE_SIZE,
            ];

            if ($cursor !== '') {
                $query['cursor'] = $cursor;
            }

            $payload = $this->get('/1.0/orders/historical', $query);
            $orders = array_merge($orders, is_array($payload['data'] ?? null) ? $payload['data'] : []);

            $cursor = (string) ($payload['metadata']['next_cursor'] ?? '');
            $page++;
        } while ($cursor !== '' && $page < self::MAX_PAGES_PER_WINDOW);

        return $orders;
    }

    /**
     * Order details carry the fee, which the historical listing omits.
     *
     * @return array<string, mixed>
     */
    private function orderDetails(string $orderId): array
    {
        $payload = $this->get('/1.0/orders/'.rawurlencode($orderId));

        return is_array($payload['data'] ?? null) ? $payload['data'] : [];
    }

    /**
     * Revolut X charges the fee in the asset received, so the credited quantity
     * is the filled quantity less that fee, and the fee is converted to the
     * quote currency to match how fees are stored elsewhere.
     *
     * @param  array<string, mixed>  $order
     * @param  array<string, mixed>  $details
     * @return array{
     *     external_id: string,
     *     base_asset: string,
     *     quote_asset: string,
     *     side: string,
     *     executed_at: string,
     *     quantity: string,
     *     price_per_unit: string,
     *     fee: string
     * }
     */
    private function normalizeOrder(array $order, array $details): array
    {
        [$baseAsset, $quoteAsset] = array_pad(explode('/', (string) ($order['symbol'] ?? '')), 2, '');

        $price = (float) ($order['average_fill_price'] ?? 0);
        $quantity = (float) ($order['filled_quantity'] ?? 0);
        $totalFee = (float) ($details['total_fee'] ?? 0);
        $feeCurrency = strtoupper((string) ($details['fee_currency'] ?? ''));

        $fee = 0.0;

        if ($feeCurrency === strtoupper($baseAsset)) {
            $quantity -= $totalFee;
            $fee = $totalFee * $price;
        } elseif ($feeCurrency === strtoupper($quoteAsset)) {
            $fee = $totalFee;
        }

        return [
            'external_id' => (string) ($order['id'] ?? ''),
            'base_asset' => strtoupper($baseAsset),
            'quote_asset' => strtoupper($quoteAsset),
            'side' => strtolower((string) ($order['side'] ?? '')),
            // Stored as local wall-clock time, matching the CSV import.
            'executed_at' => CarbonImmutable::createFromTimestampMs(
                (int) ($order['created_date'] ?? 0),
                config('app.timezone'),
            )->format('Y-m-d H:i:s'),
            'quantity' => number_format(max($quantity, 0), 8, '.', ''),
            'price_per_unit' => number_format($price, 3, '.', ''),
            'fee' => number_format($fee, 2, '.', ''),
        ];
    }

    /**
     * Perform a signed GET request against the Revolut X API.
     *
     * @param  array<string, scalar>  $query
     */
    private function get(string $endpoint, array $query = []): array
    {
        $queryString = http_build_query($query, '', '&');
        $attempt = 0;

        do {
            $attempt++;
            $timestamp = (string) (int) round(microtime(true) * 1000);

            $response = Http::acceptJson()
                ->withHeaders([
                    'X-Revx-API-Key' => $this->apiKey,
                    'X-Revx-Timestamp' => $timestamp,
                    'X-Revx-Signature' => $this->sign($timestamp.'GET'.$this->signaturePath($endpoint).$queryString),
                ])
                ->timeout(30)
                ->connectTimeout(10)
                ->get($this->baseUrl.$endpoint.($queryString === '' ? '' : '?'.$queryString));

            if ($response->status() !== 429 || $attempt >= self::MAX_ATTEMPTS) {
                break;
            }

            usleep($this->retryDelay($response->header('Retry-After')));
        } while (true);

        if (! $response->successful()) {
            throw new RuntimeException('Revolut X API error: '.$response->body());
        }

        $payload = $response->json();

        return is_array($payload) ? $payload : [];
    }

    /**
     * Revolut X reports the retry delay in milliseconds.
     */
    private function retryDelay(?string $retryAfter): int
    {
        $microseconds = (int) round(((float) $retryAfter) * 1000);

        return max(self::MIN_RETRY_MICROSECONDS, min($microseconds, self::MAX_RETRY_MICROSECONDS));
    }

    /**
     * The request path as it must appear in the signed message, including the
     * path prefix carried by the base URL (for example `/api/1.0/balances`).
     */
    private function signaturePath(string $endpoint): string
    {
        return (string) parse_url($this->baseUrl, PHP_URL_PATH).$endpoint;
    }

    private function sign(string $message): string
    {
        return base64_encode(sodium_crypto_sign_detached($message, $this->secretKey()));
    }

    /**
     * Load the Ed25519 secret key from the configured PKCS#8 PEM file.
     */
    private function secretKey(): string
    {
        if ($this->secretKey !== null) {
            return $this->secretKey;
        }

        if (! is_readable($this->privateKeyPath)) {
            throw new RuntimeException('Zasebni ključ Revolut X ni dostopen: '.$this->privateKeyPath);
        }

        $contents = (string) file_get_contents($this->privateKeyPath);
        $der = base64_decode((string) preg_replace('/-----[^-]+-----|\s+/', '', $contents), true);

        if ($der === false || strlen($der) !== self::PKCS8_HEADER_LENGTH + self::ED25519_SEED_LENGTH) {
            throw new RuntimeException('Zasebni ključ Revolut X ni veljaven Ed25519 PKCS#8 ključ.');
        }

        $keyPair = sodium_crypto_sign_seed_keypair(
            substr($der, self::PKCS8_HEADER_LENGTH, self::ED25519_SEED_LENGTH),
        );

        return $this->secretKey = sodium_crypto_sign_secretkey($keyPair);
    }
}
