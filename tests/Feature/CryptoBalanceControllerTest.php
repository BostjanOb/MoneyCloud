<?php

use App\Enums\BalanceSyncProvider;
use App\Enums\InvestmentSymbolType;
use App\Models\CryptoBalance;
use App\Models\InvestmentProvider;
use App\Models\InvestmentPurchase;
use App\Models\InvestmentSymbol;
use App\Models\User;
use App\Services\CryptoBalanceSyncService;

beforeEach(function () {
    $this->withoutVite();
});

test('crypto balances page requires authentication', function () {
    $this->get(route('crypto.balances.index'))
        ->assertRedirect(route('login'));
});

test('authenticated user can view crypto balances and symbol summary without dca quantities', function () {
    $user = User::factory()->create();
    $provider = InvestmentProvider::factory()->crypto()->create([
        'name' => 'NEXO',
        'balance_sync_provider' => BalanceSyncProvider::Binance->value,
    ]);
    $ledger = InvestmentProvider::factory()->crypto('ledger', 'Ledger')->create([
        'name' => 'Ledger',
    ]);
    $symbol = InvestmentSymbol::factory()->crypto('BTC')->create([
        'current_price' => '50000.00',
    ]);

    CryptoBalance::factory()->create([
        'investment_provider_id' => $provider->id,
        'investment_symbol_id' => $symbol->id,
        'manual_quantity' => '0.50000000',
    ]);
    CryptoBalance::factory()->create([
        'investment_provider_id' => $ledger->id,
        'investment_symbol_id' => $symbol->id,
        'manual_quantity' => '0.25000000',
    ]);
    InvestmentPurchase::factory()->create([
        'investment_provider_id' => $provider->id,
        'investment_symbol_id' => $symbol->id,
        'quantity' => '0.10000000',
        'price_per_unit' => '40000.00',
        'fee' => '5.00',
    ]);

    $this->actingAs($user)
        ->get(route('crypto.balances.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Kripto/Stanja')
            ->has('providerOptions', 2)
            ->has('syncProviderOptions', 1)
            ->where('syncProviderOptions.0.name', 'NEXO')
            ->where('providerOptions.1.name', 'NEXO')
            ->has('symbolOptions', 1)
            ->where('symbolOptions.0.symbol', 'BTC')
            ->has('balanceRows', 2)
            ->where('balanceRows.1.provider_name', 'NEXO')
            ->where('balanceRows.0.symbol', 'BTC')
            ->where('balanceRows.1.manual_quantity', '0.50000000')
            ->where('balanceRows.1.current_value', '25000.00')
            ->has('symbolSummary', 1)
            ->where('symbolSummary.0.symbol', 'BTC')
            ->where('symbolSummary.0.quantity', '0.75000000')
            ->where('symbolSummary.0.current_value', '37500.00')
            ->where('symbolSummary.0.provider_count', 2)
        );
});

test('crypto balances page ignores dca purchases without explicit balances', function () {
    $user = User::factory()->create();
    $provider = InvestmentProvider::factory()->crypto()->create();
    $symbol = InvestmentSymbol::factory()->crypto('BTC')->create();

    InvestmentPurchase::factory()->create([
        'investment_provider_id' => $provider->id,
        'investment_symbol_id' => $symbol->id,
        'quantity' => '0.10000000',
        'price_per_unit' => '40000.00',
        'fee' => '5.00',
    ]);

    $this->actingAs($user)
        ->get(route('crypto.balances.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Kripto/Stanja')
            ->has('balanceRows', 0)
            ->has('symbolSummary', 0)
        );
});

test('can store update and delete a manual crypto balance', function () {
    $user = User::factory()->create();
    $provider = InvestmentProvider::factory()->crypto()->create();
    $symbol = InvestmentSymbol::factory()->crypto('ETH')->create();

    $this->actingAs($user)
        ->post(route('crypto.balances.store'), [
            'investment_provider_id' => $provider->id,
            'investment_symbol_id' => $symbol->id,
            'manual_quantity' => '1.25000000',
        ])
        ->assertRedirect();

    $balance = CryptoBalance::query()->firstOrFail();

    expect($balance->manual_quantity)->toBe('1.25000000');

    $this->actingAs($user)
        ->put(route('crypto.balances.update', $balance), [
            'investment_provider_id' => $provider->id,
            'investment_symbol_id' => $symbol->id,
            'manual_quantity' => '2.00000000',
        ])
        ->assertRedirect();

    expect($balance->fresh()->manual_quantity)->toBe('2.00000000');

    $this->actingAs($user)
        ->delete(route('crypto.balances.destroy', $balance))
        ->assertRedirect();

    $this->assertDatabaseMissing('crypto_balances', ['id' => $balance->id]);
});

test('manual crypto balance rejects duplicate provider symbol pairs', function () {
    $user = User::factory()->create();
    $provider = InvestmentProvider::factory()->crypto()->create();
    $symbol = InvestmentSymbol::factory()->crypto('BTC')->create();

    CryptoBalance::factory()->create([
        'investment_provider_id' => $provider->id,
        'investment_symbol_id' => $symbol->id,
    ]);

    $this->actingAs($user)
        ->post(route('crypto.balances.store'), [
            'investment_provider_id' => $provider->id,
            'investment_symbol_id' => $symbol->id,
            'manual_quantity' => '0.25000000',
        ])
        ->assertSessionHasErrors('investment_provider_id');
});

test('manual crypto balance rejects non crypto platforms and symbols', function () {
    $user = User::factory()->create();
    $provider = InvestmentProvider::factory()->ilirika()->create();
    $symbol = InvestmentSymbol::factory()->create([
        'type' => InvestmentSymbolType::ETF,
    ]);

    $this->actingAs($user)
        ->post(route('crypto.balances.store'), [
            'investment_provider_id' => $provider->id,
            'investment_symbol_id' => $symbol->id,
            'manual_quantity' => '0.25000000',
        ])
        ->assertSessionHasErrors([
            'investment_provider_id',
            'investment_symbol_id',
        ]);
});

test('manual crypto balance sync route triggers provider sync and flashes the result', function () {
    $user = User::factory()->create();
    $provider = InvestmentProvider::factory()->crypto('binance', 'Binance')->create([
        'balance_sync_provider' => BalanceSyncProvider::Binance->value,
    ]);
    $symbol = InvestmentSymbol::factory()->crypto('BTC')->create();

    CryptoBalance::factory()->create([
        'investment_provider_id' => $provider->id,
        'investment_symbol_id' => $symbol->id,
        'manual_quantity' => '0.10000000',
    ]);

    $mock = Mockery::mock(CryptoBalanceSyncService::class);
    $mock->shouldReceive('syncProvider')
        ->once()
        ->withArgs(fn (InvestmentProvider $selectedProvider): bool => $selectedProvider->is($provider))
        ->andReturn([
            'updated_count' => 2,
            'skipped_count' => 1,
        ]);
    app()->instance(CryptoBalanceSyncService::class, $mock);

    $this->actingAs($user)
        ->post(route('crypto.balances.sync'), [
            'investment_provider_id' => $provider->id,
        ])
        ->assertRedirect()
        ->assertSessionHas('status', 'Binance: sinhroniziranih 2 stanj, preskočenih 1.');
});
