<?php

use App\Services\BinanceService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class);

test('it returns flexible simple earn balances grouped by asset', function () {
    config([
        'services.binance.api_key' => 'test-api-key',
        'services.binance.api_secret' => 'test-api-secret',
        'services.binance.sapi_url' => 'https://api.binance.com/sapi/',
    ]);

    Http::fake([
        'https://api.binance.com/sapi/v1/simple-earn/flexible/position*' => Http::response([
            'rows' => [
                [
                    'totalAmount' => '75.46000000',
                    'tierAnnualPercentageRate' => [
                        '0-5BTC' => 0.05,
                        '5-10BTC' => 0.03,
                    ],
                    'latestAnnualPercentageRate' => '0.02599895',
                    'yesterdayAirdropPercentageRate' => '0.02599895',
                    'asset' => 'USDT',
                    'airDropAsset' => 'BETH',
                    'canRedeem' => true,
                    'collateralAmount' => '232.23123213',
                    'productId' => 'USDT001',
                    'yesterdayRealTimeRewards' => '0.10293829',
                    'cumulativeBonusRewards' => '0.22759183',
                    'cumulativeRealTimeRewards' => '0.22759183',
                    'cumulativeTotalRewards' => '0.45459183',
                    'autoSubscribe' => true,
                ],
                [
                    'totalAmount' => '4.54000000',
                    'tierAnnualPercentageRate' => [],
                    'latestAnnualPercentageRate' => '0.01000000',
                    'yesterdayAirdropPercentageRate' => '0.00000000',
                    'asset' => 'USDT',
                    'airDropAsset' => 'BETH',
                    'canRedeem' => true,
                    'collateralAmount' => '0.00000000',
                    'productId' => 'USDT002',
                    'yesterdayRealTimeRewards' => '0.01000000',
                    'cumulativeBonusRewards' => '0.00000000',
                    'cumulativeRealTimeRewards' => '0.01000000',
                    'cumulativeTotalRewards' => '0.01000000',
                    'autoSubscribe' => false,
                ],
                [
                    'totalAmount' => '0.00000000',
                    'tierAnnualPercentageRate' => [],
                    'latestAnnualPercentageRate' => '0.02599895',
                    'yesterdayAirdropPercentageRate' => '0.02599895',
                    'asset' => 'BTC',
                    'airDropAsset' => 'BETH',
                    'canRedeem' => false,
                    'collateralAmount' => '0.00000000',
                    'productId' => 'BTC001',
                    'yesterdayRealTimeRewards' => '0.00000000',
                    'cumulativeBonusRewards' => '0.00000000',
                    'cumulativeRealTimeRewards' => '0.00000000',
                    'cumulativeTotalRewards' => '0.00000000',
                    'autoSubscribe' => false,
                ],
            ],
            'total' => 3,
        ], 200),
    ]);

    $balances = app(BinanceService::class)->getFlexibleSimpleEarnBalances();

    expect($balances)->toHaveKey('USDT')
        ->and($balances['USDT']['available'])->toBe(80.0)
        ->and($balances['USDT']['onOrder'])->toBe(0.0)
        ->and($balances['USDT']['total'])->toBe(80.0)
        ->and($balances['USDT']['positions'])->toHaveCount(2)
        ->and($balances)->not->toHaveKey('BTC');

    Http::assertSent(function ($request) {
        return str_starts_with(
            $request->url(),
            'https://api.binance.com/sapi/v1/simple-earn/flexible/position?',
        )
            && $request->hasHeader('X-MBX-APIKEY', 'test-api-key')
            && str_contains($request->url(), 'size=50')
            && str_contains($request->url(), 'recvWindow=5000')
            && str_contains($request->url(), 'signature=');
    });
});

test('it returns merged spot and simple earn balance overview by asset', function () {
    config([
        'services.binance.api_key' => 'test-api-key',
        'services.binance.api_secret' => 'test-api-secret',
        'services.binance.base_url' => 'https://api.binance.com/api/',
        'services.binance.sapi_url' => 'https://api.binance.com/sapi/',
    ]);

    Http::fake([
        'https://api.binance.com/api/v3/account*' => Http::response([
            'balances' => [
                [
                    'asset' => 'BTC',
                    'free' => '0.10000000',
                    'locked' => '0.02000000',
                ],
                [
                    'asset' => 'USDT',
                    'free' => '100.00000000',
                    'locked' => '25.00000000',
                ],
                [
                    'asset' => 'XRP',
                    'free' => '0.00000000',
                    'locked' => '0.00000000',
                ],
            ],
        ], 200),
        'https://api.binance.com/sapi/v1/simple-earn/flexible/position*' => Http::response([
            'rows' => [
                [
                    'totalAmount' => '75.46000000',
                    'asset' => 'USDT',
                    'productId' => 'USDT001',
                ],
                [
                    'totalAmount' => '2.50000000',
                    'asset' => 'ETH',
                    'productId' => 'ETH001',
                ],
            ],
            'total' => 2,
        ], 200),
    ]);

    $overview = app(BinanceService::class)->getBalanceOverview();

    expect($overview)->toBe([
        'BTC' => 0.12,
        'USDT' => 200.46,
        'ETH' => 2.5,
    ])->not->toHaveKey('XRP');

    Http::assertSentCount(2);
});
