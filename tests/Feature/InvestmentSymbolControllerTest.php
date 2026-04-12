<?php

use App\Enums\InvestmentPriceSource;
use App\Enums\InvestmentSymbolType;
use App\Models\InvestmentSymbol;
use App\Models\User;

test('investment symbols page requires authentication', function () {
    $this->get(route('investments.symbols.index'))
        ->assertRedirect(route('login'));
});

test('authenticated user can view symbols pages', function () {
    $user = User::factory()->create();
    $symbol = InvestmentSymbol::factory()->create([
        'type' => InvestmentSymbolType::ETF,
        'symbol' => 'VWCE',
    ]);

    $this->actingAs($user)
        ->get(route('investments.symbols.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Investicije/Simboli')
            ->has('symbols', 1)
            ->where('symbols.0.symbol', $symbol->symbol)
            ->where('symbols.0.external_source_id', null)
            ->where('symbols.0.price_synced_at', null)
            ->where('refreshableCoinMarketCapCount', 0)
            ->where('refreshableYfApiCount', 0)
            ->where('refreshableLjseCount', 0)
            ->has('typeOptions', 4)
            ->where('filters.type', null)
        );

    $this->actingAs($user)
        ->get(route('investments.symbols.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Investicije/SimboliForm')
            ->where('symbol', null)
            ->has('typeOptions', 4)
            ->has('priceSourceOptions', 4)
        );

    $this->actingAs($user)
        ->get(route('investments.symbols.edit', $symbol))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Investicije/SimboliForm')
            ->where('symbol.symbol', $symbol->symbol)
            ->where('symbol.external_source_id', null)
            ->has('priceSourceOptions', 4)
        );
});

test('investment symbols page can be filtered by type', function () {
    $user = User::factory()->create();

    $crypto = InvestmentSymbol::factory()->create([
        'type' => InvestmentSymbolType::CRYPTO,
        'symbol' => 'BTC',
        'price_source' => InvestmentPriceSource::COINMARKETCAP->value,
        'external_source_id' => '1',
    ]);
    InvestmentSymbol::factory()->create([
        'type' => InvestmentSymbolType::ETF,
        'symbol' => 'VWCE',
        'price_source' => InvestmentPriceSource::YFAPI->value,
        'external_source_id' => 'VWCE.DE',
    ]);
    InvestmentSymbol::factory()->create([
        'type' => InvestmentSymbolType::BOND,
        'symbol' => 'RS94',
        'price_source' => InvestmentPriceSource::LJSE->value,
        'external_source_id' => 'RS94',
    ]);

    $this->actingAs($user)
        ->get(route('investments.symbols.index', [
            'type' => InvestmentSymbolType::CRYPTO->value,
        ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Investicije/Simboli')
            ->has('symbols', 1)
            ->where('symbols.0.symbol', $crypto->symbol)
            ->where('refreshableCoinMarketCapCount', 1)
            ->where('refreshableYfApiCount', 1)
            ->where('refreshableLjseCount', 1)
            ->where('filters.type', InvestmentSymbolType::CRYPTO->value)
            ->has('typeOptions', 4)
        );
});

test('can store update and delete an investment symbol', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('investments.symbols.store'), [
            'type' => InvestmentSymbolType::CRYPTO->value,
            'symbol' => 'btc',
            'isin' => null,
            'taxable' => true,
            'price_source' => InvestmentPriceSource::COINMARKETCAP->value,
            'external_source_id' => '1',
            'current_price' => '65000.00',
        ])
        ->assertRedirect(route('investments.symbols.index'));

    $symbol = InvestmentSymbol::query()->firstOrFail();

    expect($symbol->symbol)->toBe('BTC')
        ->and($symbol->type)->toBe(InvestmentSymbolType::CRYPTO)
        ->and($symbol->external_source_id)->toBe('1')
        ->and($symbol->price_source)->toBe(InvestmentPriceSource::COINMARKETCAP->value);

    $this->actingAs($user)
        ->put(route('investments.symbols.update', $symbol), [
            'type' => InvestmentSymbolType::ETF->value,
            'symbol' => 'BTC',
            'isin' => null,
            'taxable' => false,
            'price_source' => InvestmentPriceSource::MANUAL->value,
            'external_source_id' => 'SHOULD-CLEAR',
            'current_price' => '66000.00',
        ])
        ->assertRedirect(route('investments.symbols.index'));

    expect($symbol->fresh()->taxable)->toBeFalse()
        ->and($symbol->fresh()->price_source)->toBe(InvestmentPriceSource::MANUAL->value)
        ->and($symbol->fresh()->external_source_id)->toBeNull()
        ->and($symbol->fresh()->current_price)->toBe('66000.00');

    $this->actingAs($user)
        ->delete(route('investments.symbols.destroy', $symbol))
        ->assertRedirect(route('investments.symbols.index'));

    $this->assertDatabaseMissing('investment_symbols', ['id' => $symbol->id]);
});
