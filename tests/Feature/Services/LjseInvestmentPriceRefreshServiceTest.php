<?php

use App\Enums\InvestmentPriceSource;
use App\Enums\InvestmentSymbolType;
use App\Models\InvestmentSymbol;
use App\Services\LjseInvestmentPriceRefreshService;
use Illuminate\Support\Facades\Http;

function ljsePriceListResponse(array $rowsBySegment, string $marketDataDate = '2026-04-10'): array
{
    return [
        'market_data_date' => $marketDataDate,
        'priceList' => collect($rowsBySegment)
            ->map(fn (array $rows, string $segment): array => [
                'market_segment_id' => $segment,
                'tradingPriceList' => [
                    'rows' => $rows,
                ],
            ])
            ->values()
            ->all(),
    ];
}

test('it updates stock and bond prices from ljse', function () {
    $stock = InvestmentSymbol::factory()->create([
        'type' => InvestmentSymbolType::STOCK,
        'symbol' => 'KRKG',
        'price_source' => InvestmentPriceSource::LJSE->value,
        'external_source_id' => 'KRKG',
        'current_price' => '230.00',
    ]);
    $bond = InvestmentSymbol::factory()->bond()->create([
        'symbol' => 'RS94',
        'price_source' => InvestmentPriceSource::LJSE->value,
        'external_source_id' => 'RS94',
        'current_price' => '999.00',
    ]);

    Http::fake([
        'ljse.si/json/TradingPriceList*' => Http::response(ljsePriceListResponse([
            'A' => [
                [
                    'symbol' => 'KRKG',
                    'last_price_n' => 239.5,
                    'date' => '2026-04-10T00:00:00',
                ],
            ],
            'D' => [
                [
                    'symbol' => 'RS94',
                    'last_price_n' => 100.5,
                    'date' => '2026-04-10T00:00:00',
                ],
            ],
        ])),
    ]);

    $result = app(LjseInvestmentPriceRefreshService::class)->refresh();

    expect($result)->toBe([
        'updated_count' => 2,
        'skipped_count' => 0,
        'failed_symbols' => [],
    ]);

    expect($stock->fresh()->current_price)->toBe('239.50')
        ->and($stock->fresh()->price_synced_at?->toIso8601String())->toBe('2026-04-10T00:00:00+02:00')
        ->and($bond->fresh()->current_price)->toBe('1005.00')
        ->and($bond->fresh()->price_source)->toBe(InvestmentPriceSource::LJSE->value);
});

test('it records failed ljse symbols when the response is missing usable prices', function () {
    $stock = InvestmentSymbol::factory()->create([
        'type' => InvestmentSymbolType::STOCK,
        'symbol' => 'KRKG',
        'price_source' => InvestmentPriceSource::LJSE->value,
        'external_source_id' => 'KRKG',
        'current_price' => '230.00',
    ]);
    $bond = InvestmentSymbol::factory()->bond()->create([
        'symbol' => 'RS96',
        'price_source' => InvestmentPriceSource::LJSE->value,
        'external_source_id' => 'RS96',
        'current_price' => '990.00',
    ]);

    Http::fake([
        'ljse.si/json/TradingPriceList*' => Http::response(ljsePriceListResponse([
            'A' => [
                [
                    'symbol' => 'KRKG',
                    'last_price_n' => 239.5,
                    'date' => '2026-04-10T00:00:00',
                ],
            ],
            'D' => [
                [
                    'symbol' => 'RS96',
                    'last_price_n' => 0,
                    'date' => '2026-04-10T00:00:00',
                ],
            ],
        ])),
    ]);

    $result = app(LjseInvestmentPriceRefreshService::class)->refresh();

    expect($result)->toBe([
        'updated_count' => 1,
        'skipped_count' => 1,
        'failed_symbols' => ['RS96'],
    ]);

    expect($stock->fresh()->current_price)->toBe('239.50')
        ->and($bond->fresh()->current_price)->toBe('990.00');
});
