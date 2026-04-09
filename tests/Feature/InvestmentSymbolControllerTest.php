<?php

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
        );

    $this->actingAs($user)
        ->get(route('investments.symbols.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Investicije/SimboliForm')
            ->where('symbol', null)
            ->has('typeOptions', 4)
        );

    $this->actingAs($user)
        ->get(route('investments.symbols.edit', $symbol))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Investicije/SimboliForm')
            ->where('symbol.symbol', $symbol->symbol)
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
            'price_source' => 'manual',
            'current_price' => '65000.00',
        ])
        ->assertRedirect(route('investments.symbols.index'));

    $symbol = InvestmentSymbol::query()->firstOrFail();

    expect($symbol->symbol)->toBe('BTC')
        ->and($symbol->type)->toBe(InvestmentSymbolType::CRYPTO);

    $this->actingAs($user)
        ->put(route('investments.symbols.update', $symbol), [
            'type' => InvestmentSymbolType::CRYPTO->value,
            'symbol' => 'BTC',
            'isin' => null,
            'taxable' => false,
            'price_source' => 'manual-refresh',
            'current_price' => '66000.00',
        ])
        ->assertRedirect(route('investments.symbols.index'));

    expect($symbol->fresh()->taxable)->toBeFalse()
        ->and($symbol->fresh()->price_source)->toBe('manual-refresh')
        ->and($symbol->fresh()->current_price)->toBe('66000.00');

    $this->actingAs($user)
        ->delete(route('investments.symbols.destroy', $symbol))
        ->assertRedirect(route('investments.symbols.index'));

    $this->assertDatabaseMissing('investment_symbols', ['id' => $symbol->id]);
});
