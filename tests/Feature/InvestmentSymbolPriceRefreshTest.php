<?php

use App\Enums\InvestmentPriceSource;
use App\Enums\InvestmentSymbolType;
use App\Models\InvestmentSymbol;
use App\Models\User;
use App\Services\CoinMarketCapInvestmentPriceRefreshService;
use App\Services\LjseInvestmentPriceRefreshService;
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

test('manual ljse refresh route triggers ljse price refresh and flashes the result', function () {
    $user = User::factory()->create();

    $mock = Mockery::mock(LjseInvestmentPriceRefreshService::class);
    $mock->shouldReceive('refresh')->once()->andReturn([
        'updated_count' => 2,
        'skipped_count' => 1,
        'failed_symbols' => ['RS96'],
    ]);
    app()->instance(LjseInvestmentPriceRefreshService::class, $mock);

    $this->actingAs($user)
        ->post(route('investments.symbols.refresh-prices', 'ljse'))
        ->assertRedirect(route('investments.symbols.index'))
        ->assertSessionHas('status', 'Osveženih 2 simbolov, preskočenih 1. Neuspešni simboli: RS96.');
});

test('manual refresh route flashes an error when refresh fails', function () {
    $user = User::factory()->create();
    $symbol = InvestmentSymbol::factory()->crypto('ETH')->create([
        'current_price' => '1900.00',
        'price_source' => InvestmentPriceSource::COINMARKETCAP->value,
        'external_source_id' => '1027',
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

test('manual refresh route is blocked when price refresh is disabled', function () {
    config()->set('investments.allow_price_refresh', false);

    $user = User::factory()->create();

    $mock = Mockery::mock(CoinMarketCapInvestmentPriceRefreshService::class);
    $mock->shouldReceive('refresh')->never();
    app()->instance(CoinMarketCapInvestmentPriceRefreshService::class, $mock);

    $this->actingAs($user)
        ->post(route('investments.symbols.refresh-prices', 'coinmarketcap'))
        ->assertRedirect(route('investments.symbols.index'))
        ->assertSessionHas('error', 'Ročno osveževanje cen je trenutno onemogočeno.');
});

test('symbols index exposes disabled manual refresh state', function () {
    config()->set('investments.allow_price_refresh', false);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('investments.symbols.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Investicije/Simboli')
            ->where('manualPriceRefreshEnabled', false)
            ->where('features.manualPriceRefreshEnabled', false)
        );
});

test('external source id is required for non manual sources', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('investments.symbols.store'), [
            'type' => InvestmentSymbolType::ETF->value,
            'symbol' => 'VWCE',
            'isin' => null,
            'taxable' => true,
            'price_source' => InvestmentPriceSource::YFAPI->value,
            'external_source_id' => null,
            'current_price' => '148.00',
        ])
        ->assertSessionHasErrors('external_source_id');
});

test('symbols reject incompatible price sources', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('investments.symbols.store'), [
            'type' => InvestmentSymbolType::STOCK->value,
            'symbol' => 'AAPL',
            'isin' => null,
            'taxable' => true,
            'price_source' => InvestmentPriceSource::COINMARKETCAP->value,
            'external_source_id' => '1027',
            'current_price' => '200.00',
        ])
        ->assertSessionHasErrors('price_source');

    $this->actingAs($user)
        ->post(route('investments.symbols.store'), [
            'type' => InvestmentSymbolType::CRYPTO->value,
            'symbol' => 'ETH',
            'isin' => null,
            'taxable' => false,
            'price_source' => InvestmentPriceSource::YFAPI->value,
            'external_source_id' => 'ETH-USD',
            'current_price' => '3000.00',
        ])
        ->assertSessionHasErrors('price_source');
});

test('yfapi and ljse sources uppercase external source ids on store', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('investments.symbols.store'), [
            'type' => InvestmentSymbolType::ETF->value,
            'symbol' => 'VWCE',
            'isin' => null,
            'taxable' => true,
            'price_source' => InvestmentPriceSource::YFAPI->value,
            'external_source_id' => 'vwce.de',
            'current_price' => '148.00',
        ])
        ->assertRedirect(route('investments.symbols.index'));

    $this->actingAs($user)
        ->post(route('investments.symbols.store'), [
            'type' => InvestmentSymbolType::BOND->value,
            'symbol' => 'RS94',
            'isin' => null,
            'taxable' => true,
            'price_source' => InvestmentPriceSource::LJSE->value,
            'external_source_id' => 'rs94',
            'current_price' => '1005.00',
        ])
        ->assertRedirect(route('investments.symbols.index'));

    $symbols = InvestmentSymbol::query()->orderBy('symbol')->get();

    expect($symbols[0]->external_source_id)->toBe('RS94')
        ->and($symbols[1]->external_source_id)->toBe('VWCE.DE');
});

test('manual source clears external source id on store', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('investments.symbols.store'), [
            'type' => InvestmentSymbolType::STOCK->value,
            'symbol' => 'KRKG',
            'isin' => null,
            'taxable' => true,
            'price_source' => InvestmentPriceSource::MANUAL->value,
            'external_source_id' => 'KRKG',
            'current_price' => '239.50',
        ])
        ->assertRedirect(route('investments.symbols.index'));

    expect(InvestmentSymbol::query()->firstOrFail()->external_source_id)->toBeNull();
});
