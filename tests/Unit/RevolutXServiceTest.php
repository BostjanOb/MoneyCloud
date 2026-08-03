<?php

use App\Services\RevolutXService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Write a throwaway Ed25519 key pair as a PKCS#8 PEM file, mirroring the file
 * Revolut X hands out, and configure the service to use it.
 *
 * @return string the matching raw public key, for verifying signatures
 */
function fakeRevolutXCredentials(): string
{
    $keyPair = sodium_crypto_sign_keypair();
    $seed = substr(sodium_crypto_sign_secretkey($keyPair), 0, 32);
    $der = hex2bin('302e020100300506032b657004220420').$seed;

    $path = tempnam(sys_get_temp_dir(), 'revolutx').'.pem';
    file_put_contents(
        $path,
        "-----BEGIN PRIVATE KEY-----\n".chunk_split(base64_encode($der), 64, "\n")."-----END PRIVATE KEY-----\n",
    );

    config([
        'services.revolutx.api_key' => 'test-api-key',
        'services.revolutx.private_key_path' => $path,
        'services.revolutx.base_url' => 'https://revx.revolut.com/api',
    ]);

    return sodium_crypto_sign_publickey($keyPair);
}

function fakeRevolutXBalances(): void
{
    Http::fake([
        'https://revx.revolut.com/api/1.0/balances' => Http::response([
            ['currency' => 'EUR', 'available' => '250.00', 'reserved' => '0.00', 'total' => '250.00'],
            ['currency' => 'BTC', 'available' => '0.00054600', 'reserved' => '0.00000000', 'total' => '0.00054600'],
            ['currency' => 'ETH', 'available' => '0.01227245', 'reserved' => '0.00000000', 'staked' => '0.00000000', 'total' => '0.01227245'],
            ['currency' => 'USD', 'available' => '0.00', 'reserved' => '0.00', 'total' => '0.00'],
        ], 200),
    ]);
}

test('it maps balances to an overview keyed by uppercase asset and keeps zero balances', function () {
    fakeRevolutXCredentials();
    fakeRevolutXBalances();

    expect(app(RevolutXService::class)->getBalanceOverview())->toBe([
        'EUR' => 250.0,
        'BTC' => 0.000546,
        'ETH' => 0.01227245,
        'USD' => 0.0,
    ]);
});

test('it signs requests with the configured ed25519 private key', function () {
    $publicKey = fakeRevolutXCredentials();
    fakeRevolutXBalances();

    app(RevolutXService::class)->getBalanceOverview();

    Http::assertSent(function (Request $request) use ($publicKey): bool {
        $timestamp = $request->header('X-Revx-Timestamp')[0] ?? '';

        return $request->url() === 'https://revx.revolut.com/api/1.0/balances'
            && $request->hasHeader('X-Revx-API-Key', 'test-api-key')
            && ctype_digit($timestamp)
            && sodium_crypto_sign_verify_detached(
                base64_decode($request->header('X-Revx-Signature')[0] ?? '', true),
                $timestamp.'GET/api/1.0/balances',
                $publicKey,
            );
    });

    Http::assertSentCount(1);
});

test('it reports missing configuration', function () {
    config([
        'services.revolutx.api_key' => null,
        'services.revolutx.private_key_path' => null,
        'services.revolutx.base_url' => 'https://revx.revolut.com/api',
    ]);

    expect(app(RevolutXService::class)->isConfigured())->toBeFalse();

    $publicKey = fakeRevolutXCredentials();

    expect($publicKey)->toBeString()
        ->and(app(RevolutXService::class)->isConfigured())->toBeTrue();
});

test('it rejects a private key that is not an ed25519 pkcs8 key', function () {
    fakeRevolutXCredentials();
    fakeRevolutXBalances();

    file_put_contents(
        config('services.revolutx.private_key_path'),
        "-----BEGIN PRIVATE KEY-----\n".base64_encode('not-a-key')."\n-----END PRIVATE KEY-----\n",
    );

    expect(fn () => app(RevolutXService::class)->getBalanceOverview())
        ->toThrow(RuntimeException::class, 'Zasebni ključ Revolut X ni veljaven Ed25519 PKCS#8 ključ.');

    Http::assertNothingSent();
});

test('it throws when the api rejects the request', function () {
    fakeRevolutXCredentials();

    Http::fake([
        'https://revx.revolut.com/api/1.0/balances' => Http::response('Unauthorized', 401),
    ]);

    expect(fn () => app(RevolutXService::class)->getBalanceOverview())
        ->toThrow(RuntimeException::class, 'Revolut X API error: Unauthorized');
});
