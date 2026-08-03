<?php

namespace App\Services;

use App\Contracts\CryptoExchangeClient;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class RevolutXService implements CryptoExchangeClient
{
    /**
     * Length of the PKCS#8 DER header that precedes the raw Ed25519 seed.
     */
    private const PKCS8_HEADER_LENGTH = 16;

    private const ED25519_SEED_LENGTH = 32;

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
     * Perform a signed GET request against the Revolut X API.
     *
     * @param  array<string, scalar>  $query
     */
    private function get(string $endpoint, array $query = []): array
    {
        $queryString = http_build_query($query, '', '&');
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

        if (! $response->successful()) {
            throw new RuntimeException('Revolut X API error: '.$response->body());
        }

        $payload = $response->json();

        return is_array($payload) ? $payload : [];
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
