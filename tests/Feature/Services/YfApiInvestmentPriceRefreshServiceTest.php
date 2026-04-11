<?php

use App\Enums\InvestmentSymbolType;
use App\Models\InvestmentSymbol;
use App\Services\YfApiInvestmentPriceRefreshService;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config(['services.yfapi.key' => 'test-key']);
    config(['services.yfapi.base_url' => 'https://yfapi.net']);
});

test('it updates prices for symbols with yfapi_symbol set', function () {
    $symbol = InvestmentSymbol::factory()->create([
        'type' => InvestmentSymbolType::ETF,
        'symbol' => 'VWCE',
        'yfapi_symbol' => 'VWCE.DE',
        'current_price' => '100.00',
    ]);

    Http::fake([
        'yfapi.net/v6/finance/quote*' => Http::response([
            'quoteResponse' => [
                'result' => [
                    [
                        'symbol' => 'VWCE.DE',
                        'regularMarketPrice' => 148.56,
                        'regularMarketTime' => 1775835350,
                    ],
                ],
                'error' => null,
            ],
        ]),
    ]);

    $result = app(YfApiInvestmentPriceRefreshService::class)->refresh();

    expect($result['updated_count'])->toBe(1)
        ->and($result['skipped_count'])->toBe(0)
        ->and($result['failed_symbols'])->toBe([]);

    $symbol->refresh();

    expect($symbol->current_price)->toBe('148.56')
        ->and($symbol->price_source)->toBe('yfapi')
        ->and($symbol->price_synced_at)->not->toBeNull();
});

test('it batches symbols in groups of ten', function () {
    InvestmentSymbol::factory()->count(12)->sequence(
        ...collect(range(1, 12))->map(fn (int $i) => [
            'symbol' => "SYM{$i}",
            'yfapi_symbol' => "SYM{$i}.DE",
            'type' => InvestmentSymbolType::STOCK,
        ])->all(),
    )->create();

    Http::fake([
        'yfapi.net/v6/finance/quote*' => Http::response([
            'quoteResponse' => [
                'result' => [],
                'error' => null,
            ],
        ]),
    ]);

    app(YfApiInvestmentPriceRefreshService::class)->refresh();

    Http::assertSentCount(2);
});

test('it skips symbols without yfapi_symbol', function () {
    InvestmentSymbol::factory()->create([
        'type' => InvestmentSymbolType::CRYPTO,
        'symbol' => 'BTC',
        'coinmarketcap_id' => 1,
        'yfapi_symbol' => null,
    ]);

    Http::fake();

    $result = app(YfApiInvestmentPriceRefreshService::class)->refresh();

    expect($result['updated_count'])->toBe(0)
        ->and($result['skipped_count'])->toBe(0);

    Http::assertNothingSent();
});

test('it throws when api key is missing', function () {
    config(['services.yfapi.key' => null]);

    InvestmentSymbol::factory()->create([
        'type' => InvestmentSymbolType::ETF,
        'symbol' => 'VWCE',
        'yfapi_symbol' => 'VWCE.DE',
    ]);

    app(YfApiInvestmentPriceRefreshService::class)->refresh();
})->throws(RuntimeException::class, 'YF API ključ ni nastavljen.');

test('it records failed symbols when quote is missing from response', function () {
    InvestmentSymbol::factory()->create([
        'type' => InvestmentSymbolType::ETF,
        'symbol' => 'VWCE',
        'yfapi_symbol' => 'VWCE.DE',
        'current_price' => '100.00',
    ]);

    Http::fake([
        'yfapi.net/v6/finance/quote*' => Http::response([
            'quoteResponse' => [
                'result' => [],
                'error' => null,
            ],
        ]),
    ]);

    $result = app(YfApiInvestmentPriceRefreshService::class)->refresh();

    expect($result['updated_count'])->toBe(0)
        ->and($result['skipped_count'])->toBe(1)
        ->and($result['failed_symbols'])->toBe(['VWCE']);
});
