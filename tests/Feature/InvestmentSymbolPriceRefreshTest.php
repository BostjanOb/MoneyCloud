<?php

use App\Enums\InvestmentSymbolType;
use App\Models\InvestmentSymbol;
use App\Models\User;
use App\Services\CoinMarketCapInvestmentPriceRefreshService;
use App\Services\YfApiInvestmentPriceRefreshService;

test('manual coinmarketcap refresh route triggers crypto price refresh and flashes the result', function () {
    $user = User::factory()->create();

    $mock = Mockery::mock(CoinMarketCapInvestmentPriceRefreshService::class);
    $mock->shouldReceive('refresh')->once()->andReturn([
        'updated_count' => 2,
        'skipped_count' => 1,
        'failed_symbols' => ['CRO'],
    ]);
    app()->instance(CoinMarketCapInvestmentPriceRefreshService::class, $mock);

    $this->actingAs($user)
        ->post(route('investments.symbols.refresh-prices', 'coinmarketcap'))
        ->assertRedirect(route('investments.symbols.index'))
        ->assertSessionHas('status', 'Osveženih 2 simbolov, preskočenih 1. Neuspešni simboli: CRO.');
});

test('manual yfapi refresh route triggers stock price refresh and flashes the result', function () {
    $user = User::factory()->create();

    $mock = Mockery::mock(YfApiInvestmentPriceRefreshService::class);
    $mock->shouldReceive('refresh')->once()->andReturn([
        'updated_count' => 3,
        'skipped_count' => 0,
        'failed_symbols' => [],
    ]);
    app()->instance(YfApiInvestmentPriceRefreshService::class, $mock);

    $this->actingAs($user)
        ->post(route('investments.symbols.refresh-prices', 'yfapi'))
        ->assertRedirect(route('investments.symbols.index'))
        ->assertSessionHas('status', 'Osveženih 3 simbolov, preskočenih 0.');
});

test('manual refresh route flashes an error when refresh fails', function () {
    $user = User::factory()->create();
    $symbol = InvestmentSymbol::factory()->crypto('ETH')->create([
        'current_price' => '1900.00',
        'coinmarketcap_id' => 1027,
    ]);

    $mock = Mockery::mock(CoinMarketCapInvestmentPriceRefreshService::class);
    $mock->shouldReceive('refresh')->once()->andThrow(
        new RuntimeException('CoinMarketCap API ključ ni nastavljen.')
    );
    app()->instance(CoinMarketCapInvestmentPriceRefreshService::class, $mock);

    $this->actingAs($user)
        ->post(route('investments.symbols.refresh-prices', 'coinmarketcap'))
        ->assertRedirect(route('investments.symbols.index'))
        ->assertSessionHas('error', 'CoinMarketCap API ključ ni nastavljen.');

    expect($symbol->fresh()->current_price)->toBe('1900.00');
});

test('refresh prices route rejects invalid source', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('investments.symbols.refresh-prices', 'invalid'))
        ->assertNotFound();
});

test('non crypto symbols ignore coinmarketcap id on store and update', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('investments.symbols.store'), [
            'type' => InvestmentSymbolType::STOCK->value,
            'symbol' => 'AAPL',
            'isin' => null,
            'taxable' => true,
            'price_source' => 'manual',
            'coinmarketcap_id' => 1027,
            'current_price' => '200.00',
        ])
        ->assertRedirect(route('investments.symbols.index'));

    $symbol = InvestmentSymbol::query()->firstOrFail();

    expect($symbol->coinmarketcap_id)->toBeNull()
        ->and($symbol->price_source)->toBe('manual');

    $this->actingAs($user)
        ->put(route('investments.symbols.update', $symbol), [
            'type' => InvestmentSymbolType::ETF->value,
            'symbol' => 'AAPL',
            'isin' => null,
            'taxable' => false,
            'price_source' => 'manual-refresh',
            'coinmarketcap_id' => 9999,
            'current_price' => '210.00',
        ])
        ->assertRedirect(route('investments.symbols.index'));

    expect($symbol->fresh()->coinmarketcap_id)->toBeNull()
        ->and($symbol->fresh()->price_source)->toBe('manual-refresh');
});

test('yfapi_symbol auto-sets price source to yfapi for non-crypto symbols', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('investments.symbols.store'), [
            'type' => InvestmentSymbolType::ETF->value,
            'symbol' => 'VWCE',
            'isin' => null,
            'taxable' => true,
            'price_source' => 'manual',
            'yfapi_symbol' => 'vwce.de',
            'current_price' => '148.00',
        ])
        ->assertRedirect(route('investments.symbols.index'));

    $symbol = InvestmentSymbol::query()->firstOrFail();

    expect($symbol->yfapi_symbol)->toBe('VWCE.DE')
        ->and($symbol->price_source)->toBe('yfapi');
});

test('crypto symbols ignore yfapi_symbol on store', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('investments.symbols.store'), [
            'type' => InvestmentSymbolType::CRYPTO->value,
            'symbol' => 'ETH',
            'isin' => null,
            'taxable' => false,
            'price_source' => 'manual',
            'coinmarketcap_id' => 1027,
            'yfapi_symbol' => 'ETH-USD',
            'current_price' => '3000.00',
        ])
        ->assertRedirect(route('investments.symbols.index'));

    $symbol = InvestmentSymbol::query()->firstOrFail();

    expect($symbol->yfapi_symbol)->toBeNull()
        ->and($symbol->coinmarketcap_id)->toBe(1027)
        ->and($symbol->price_source)->toBe('coinmarketcap');
});
