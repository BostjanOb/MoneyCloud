<?php

use App\Services\RevolutXService;
use Carbon\CarbonImmutable;
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

test('it normalises filled orders to net quantity and a fee in the quote currency', function () {
    fakeRevolutXCredentials();

    Http::fake([
        'https://revx.revolut.com/api/1.0/orders/historical*' => Http::response([
            'data' => [
                [
                    'id' => '8d179909-bbed-4487-9d65-5b8c9ec48514',
                    'symbol' => 'BTC/EUR',
                    'side' => 'buy',
                    'type' => 'market',
                    'filled_quantity' => '0.0005465',
                    'filled_amount' => '30',
                    'average_fill_price' => '54895.02',
                    'status' => 'filled',
                    'created_date' => 1785700965066,
                ],
                [
                    'id' => 'cancelled-order',
                    'symbol' => 'ETH/EUR',
                    'side' => 'buy',
                    'filled_quantity' => '0',
                    'average_fill_price' => '0',
                    'status' => 'cancelled',
                    'created_date' => 1785700965089,
                ],
            ],
            'metadata' => ['next_cursor' => ''],
        ], 200),
        'https://revx.revolut.com/api/1.0/orders/8d179909-bbed-4487-9d65-5b8c9ec48514' => Http::response([
            'data' => ['total_fee' => '0.0000005', 'fee_currency' => 'BTC'],
        ], 200),
    ]);

    $orders = app(RevolutXService::class)->getFilledOrders(
        CarbonImmutable::parse('2026-07-27 00:00:00'),
        CarbonImmutable::parse('2026-08-03 00:00:00'),
    );

    expect($orders)->toBe([[
        'external_id' => '8d179909-bbed-4487-9d65-5b8c9ec48514',
        'base_asset' => 'BTC',
        'quote_asset' => 'EUR',
        'side' => 'buy',
        // 1785700965066 is 20:02:45 UTC, stored as 22:02:45 Europe/Ljubljana.
        'executed_at' => '2026-08-02 22:02:45',
        // 0.0005465 filled less the 0.0000005 BTC fee, matching the exchange balance.
        'quantity' => '0.00054600',
        'price_per_unit' => '54895.020',
        // 0.0000005 BTC * 54895.02 EUR
        'fee' => '0.03',
    ]]);
});

test('it keeps the filled quantity when the fee is charged in the quote currency', function () {
    fakeRevolutXCredentials();

    Http::fake([
        'https://revx.revolut.com/api/1.0/orders/historical*' => Http::response([
            'data' => [[
                'id' => 'eur-fee-order',
                'symbol' => 'ETH/EUR',
                'side' => 'sell',
                'filled_quantity' => '2',
                'average_fill_price' => '1500',
                'status' => 'filled',
                'created_date' => 1785700965000,
            ]],
            'metadata' => ['next_cursor' => ''],
        ], 200),
        'https://revx.revolut.com/api/1.0/orders/eur-fee-order' => Http::response([
            'data' => ['total_fee' => '2.55', 'fee_currency' => 'EUR'],
        ], 200),
    ]);

    $orders = app(RevolutXService::class)->getFilledOrders(
        CarbonImmutable::parse('2026-08-01 00:00:00'),
        CarbonImmutable::parse('2026-08-03 00:00:00'),
    );

    expect($orders[0]['quantity'])->toBe('2.00000000')
        ->and($orders[0]['fee'])->toBe('2.55')
        ->and($orders[0]['side'])->toBe('sell');
});

test('it splits long periods into windows the api accepts', function () {
    fakeRevolutXCredentials();

    Http::fake([
        'https://revx.revolut.com/api/1.0/orders/historical*' => Http::response([
            'data' => [],
            'metadata' => ['next_cursor' => ''],
        ], 200),
    ]);

    $from = CarbonImmutable::parse('2026-05-25 00:00:00');
    $to = CarbonImmutable::parse('2026-08-03 00:00:00');

    app(RevolutXService::class)->getFilledOrders($from, $to);

    $windows = [];

    Http::assertSent(function (Request $request) use (&$windows): bool {
        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
        $windows[] = [(int) $query['start_date'], (int) $query['end_date']];

        return true;
    });

    // 70 days cannot be requested at once; the API caps a query at 30 days.
    expect($windows)->toHaveCount(3);

    foreach ($windows as [$start, $end]) {
        expect(($end - $start) / 86400000)->toBeLessThanOrEqual(30);
    }

    expect($windows[0][0])->toBe($from->getTimestampMs())
        ->and($windows[1][0])->toBe($windows[0][1])
        ->and($windows[2][0])->toBe($windows[1][1])
        ->and($windows[2][1])->toBe($to->getTimestampMs());
});

test('it follows the cursor until the api stops returning one', function () {
    fakeRevolutXCredentials();

    Http::fake([
        'https://revx.revolut.com/api/1.0/orders/historical*' => Http::sequence()
            ->push(['data' => [], 'metadata' => ['next_cursor' => 'page-2']], 200)
            ->push(['data' => [], 'metadata' => ['next_cursor' => '']], 200),
    ]);

    app(RevolutXService::class)->getFilledOrders(
        CarbonImmutable::parse('2026-08-01 00:00:00'),
        CarbonImmutable::parse('2026-08-03 00:00:00'),
    );

    Http::assertSentCount(2);
    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'cursor=page-2'));
});

test('it retries once the rate limit window has passed', function () {
    fakeRevolutXCredentials();

    Http::fake([
        'https://revx.revolut.com/api/1.0/balances' => Http::sequence()
            ->push(['message' => 'Rate limit exceeded'], 429, ['Retry-After' => '10'])
            ->push([['currency' => 'BTC', 'total' => '0.5']], 200),
    ]);

    expect(app(RevolutXService::class)->getBalanceOverview())->toBe(['BTC' => 0.5]);

    Http::assertSentCount(2);
});

test('it throws when the api rejects the request', function () {
    fakeRevolutXCredentials();

    Http::fake([
        'https://revx.revolut.com/api/1.0/balances' => Http::response('Unauthorized', 401),
    ]);

    expect(fn () => app(RevolutXService::class)->getBalanceOverview())
        ->toThrow(RuntimeException::class, 'Revolut X API error: Unauthorized');
});
