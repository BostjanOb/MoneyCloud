<?php

use App\Enums\InvestmentPriceSource;
use App\Models\InvestmentSymbol;
use App\Services\CoinMarketCapInvestmentPriceRefreshService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('it refreshes all crypto symbols with a configured external source id', function () {
    Config::set('services.coinmarketcap.key', 'test-key');
    Config::set('services.coinmarketcap.base_url', 'https://pro-api.coinmarketcap.com');

    $cro = InvestmentSymbol::factory()->crypto('CRO')->create([
        'price_source' => InvestmentPriceSource::COINMARKETCAP->value,
        'external_source_id' => '3635',
        'current_price' => '0.05',
    ]);
    $eth = InvestmentSymbol::factory()->crypto('ETH')->create([
        'price_source' => InvestmentPriceSource::COINMARKETCAP->value,
        'external_source_id' => '1027',
        'current_price' => '1900.00',
    ]);
    $btc = InvestmentSymbol::factory()->crypto('BTC')->create([
        'price_source' => InvestmentPriceSource::MANUAL->value,
        'external_source_id' => null,
        'current_price' => '50000.00',
    ]);

    Http::fake([
        'https://pro-api.coinmarketcap.com/v3/cryptocurrency/quotes/latest*' => Http::response([
            'data' => [
                [
                    'id' => 3635,
                    'symbol' => 'CRO',
                    'quote' => [
                        [
                            'symbol' => 'EUR',
                            'price' => 0.059797414615540831,
                            'last_updated' => '2026-04-11T20:01:04.000Z',
                        ],
                    ],
                ],
                [
                    'id' => 1027,
                    'symbol' => 'ETH',
                    'quote' => [
                        [
                            'symbol' => 'EUR',
                            'price' => 1974.235391676418,
                            'last_updated' => '2026-04-11T20:01:04.000Z',
                        ],
                    ],
                ],
            ],
            'status' => [
                'error_code' => '0',
                'error_message' => '',
            ],
        ], 200),
    ]);

    $result = (new CoinMarketCapInvestmentPriceRefreshService)->refresh();

    expect($result)->toBe([
        'updated_count' => 2,
        'skipped_count' => 0,
        'failed_symbols' => [],
    ]);

    expect($cro->fresh()->current_price)->toBe('0.06')
        ->and($cro->fresh()->price_source)->toBe(InvestmentPriceSource::COINMARKETCAP->value)
        ->and($cro->fresh()->price_synced_at?->toIso8601String())->toBe('2026-04-11T20:01:04+02:00')
        ->and($eth->fresh()->current_price)->toBe('1974.24')
        ->and($btc->fresh()->current_price)->toBe('50000.00');

    Http::assertSent(function ($request) {
        return str_starts_with(
            $request->url(),
            'https://pro-api.coinmarketcap.com/v3/cryptocurrency/quotes/latest?',
        )
            && $request->hasHeader('X-CMC_PRO_API_KEY', 'test-key')
            && str_contains($request->url(), 'id=3635%2C1027')
            && str_contains($request->url(), 'convert=EUR');
    });
});

test('it skips symbols that are missing from the coinmarketcap response', function () {
    Config::set('services.coinmarketcap.key', 'test-key');
    Config::set('services.coinmarketcap.base_url', 'https://pro-api.coinmarketcap.com');

    $cro = InvestmentSymbol::factory()->crypto('CRO')->create([
        'price_source' => InvestmentPriceSource::COINMARKETCAP->value,
        'external_source_id' => '3635',
        'current_price' => '0.05',
    ]);
    $eth = InvestmentSymbol::factory()->crypto('ETH')->create([
        'price_source' => InvestmentPriceSource::COINMARKETCAP->value,
        'external_source_id' => '1027',
        'current_price' => '1900.00',
    ]);

    Http::fake([
        'https://pro-api.coinmarketcap.com/v3/cryptocurrency/quotes/latest*' => Http::response([
            'data' => [
                [
                    'id' => 1027,
                    'symbol' => 'ETH',
                    'quote' => [
                        [
                            'symbol' => 'EUR',
                            'price' => 1974.235391676418,
                            'last_updated' => '2026-04-11T20:01:04.000Z',
                        ],
                    ],
                ],
            ],
            'status' => [
                'error_code' => '0',
                'error_message' => '',
            ],
        ], 200),
    ]);

    $result = (new CoinMarketCapInvestmentPriceRefreshService)->refresh();

    expect($result)->toBe([
        'updated_count' => 1,
        'skipped_count' => 1,
        'failed_symbols' => ['CRO'],
    ]);

    expect($cro->fresh()->current_price)->toBe('0.05')
        ->and($cro->fresh()->price_synced_at)->toBeNull()
        ->and($eth->fresh()->current_price)->toBe('1974.24');
});

test('it throws when the coinmarketcap api key is missing', function () {
    Config::set('services.coinmarketcap.key', null);

    InvestmentSymbol::factory()->crypto('ETH')->create([
        'price_source' => InvestmentPriceSource::COINMARKETCAP->value,
        'external_source_id' => '1027',
    ]);

    expect(fn () => (new CoinMarketCapInvestmentPriceRefreshService)->refresh())
        ->toThrow(RuntimeException::class, 'CoinMarketCap API ključ ni nastavljen.');
});

test('it throws when coinmarketcap returns an error response', function () {
    Config::set('services.coinmarketcap.key', 'test-key');
    Config::set('services.coinmarketcap.base_url', 'https://pro-api.coinmarketcap.com');

    InvestmentSymbol::factory()->crypto('ETH')->create([
        'price_source' => InvestmentPriceSource::COINMARKETCAP->value,
        'external_source_id' => '1027',
    ]);

    Http::fake([
        'https://pro-api.coinmarketcap.com/v3/cryptocurrency/quotes/latest*' => Http::response([
            'status' => [
                'error_code' => '1002',
                'error_message' => 'Neveljaven API ključ.',
            ],
        ], 401),
    ]);

    expect(fn () => (new CoinMarketCapInvestmentPriceRefreshService)->refresh())
        ->toThrow(RuntimeException::class, 'Neveljaven API ključ.');
});
