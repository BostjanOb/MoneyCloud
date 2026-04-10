<?php

use App\Enums\InvestmentSymbolType;
use App\Models\CryptoBalance;
use App\Models\InvestmentProvider;
use App\Models\InvestmentPurchase;
use App\Models\InvestmentSymbol;
use App\Models\SavingsAccount;
use App\Models\User;

beforeEach(function () {
    $this->withoutVite();
});

test('crypto dca page requires authentication', function () {
    $this->get(route('crypto.dca.index'))
        ->assertRedirect(route('login'));
});

test('crypto dca page returns symbol split data', function () {
    $user = User::factory()->create();
    $provider = InvestmentProvider::factory()->crypto()->create([
        'name' => 'Binance',
    ]);
    $btc = InvestmentSymbol::factory()->crypto('BTC')->create([
        'current_price' => '50000.00',
    ]);
    $eth = InvestmentSymbol::factory()->crypto('ETH')->create([
        'current_price' => '3000.00',
    ]);

    InvestmentPurchase::factory()->create([
        'investment_provider_id' => $provider->id,
        'investment_symbol_id' => $btc->id,
        'quantity' => '0.10000000',
        'price_per_unit' => '40000.00',
        'fee' => '5.00',
    ]);

    $this->actingAs($user)
        ->get(route('crypto.dca.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Kripto/Dca')
            ->has('symbolGroups', 1)
            ->where('symbolGroups.0.symbol.symbol', 'BTC')
            ->where('symbolGroups.0.summary.quantity', '0.10000000')
            ->where('symbolGroups.0.summary.purchase_value', '4000.00')
            ->where('symbolGroups.0.summary.fees', '5.00')
            ->where('symbolGroups.0.summary.total_cost', '4005.00')
            ->where('symbolGroups.0.summary.current_value', '5000.00')
            ->has('symbolOptions', 2)
            ->where('symbolOptions.1.symbol', $eth->symbol)
        );
});

test('crypto dca page returns empty groups before first purchase', function () {
    $user = User::factory()->create();
    $provider = InvestmentProvider::factory()->crypto()->create([
        'name' => 'Binance',
    ]);

    InvestmentSymbol::factory()->crypto('BTC')->create();

    $this->actingAs($user)
        ->get(route('crypto.dca.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Kripto/Dca')
            ->has('providerOptions', 1)
            ->where('providerOptions.0.name', $provider->name)
            ->has('symbolOptions', 1)
            ->where('symbolOptions.0.symbol', 'BTC')
            ->has('symbolGroups', 0)
        );
});

test('can store update and delete a crypto dca purchase without debiting savings or touching balances', function () {
    $user = User::factory()->create();
    $linkedAccount = SavingsAccount::factory()->create([
        'owner' => 'bostjan',
        'amount' => '1000.00',
    ]);
    $provider = InvestmentProvider::factory()->crypto()->create([
        'linked_savings_account_id' => $linkedAccount->id,
        'requires_linked_savings_account' => true,
    ]);
    $symbol = InvestmentSymbol::factory()->crypto('BTC')->create();

    $payload = [
        'investment_provider_id' => $provider->id,
        'investment_symbol_id' => $symbol->id,
        'purchased_at' => now()->toDateTimeString(),
        'quantity' => '0.10000000',
        'price_per_unit' => '40000.00',
        'fee' => '5.00',
    ];

    $this->actingAs($user)
        ->post(route('crypto.dca.store'), $payload)
        ->assertRedirect();

    $purchase = InvestmentPurchase::query()->firstOrFail();

    expect($linkedAccount->fresh()->amount)->toBe('1000.00')
        ->and($purchase->quantity)->toBe('0.10000000');
    expect(CryptoBalance::query()->count())->toBe(0);

    $this->actingAs($user)
        ->put(route('crypto.dca.update', $purchase), [
            ...$payload,
            'quantity' => '0.20000000',
        ])
        ->assertRedirect();

    expect($linkedAccount->fresh()->amount)->toBe('1000.00')
        ->and($purchase->fresh()->quantity)->toBe('0.20000000');
    expect(CryptoBalance::query()->count())->toBe(0);

    $this->actingAs($user)
        ->delete(route('crypto.dca.destroy', $purchase))
        ->assertRedirect();

    expect($linkedAccount->fresh()->amount)->toBe('1000.00');
    expect(CryptoBalance::query()->count())->toBe(0);
    $this->assertDatabaseMissing('investment_purchases', ['id' => $purchase->id]);
});

test('creating a crypto dca purchase can add quantity to a selected balance provider', function () {
    $user = User::factory()->create();
    $purchaseProvider = InvestmentProvider::factory()->crypto('binance', 'Binance')->create();
    $balanceProvider = InvestmentProvider::factory()->crypto('ledger', 'Ledger')->create();
    $symbol = InvestmentSymbol::factory()->crypto('BTC')->create();

    $this->actingAs($user)
        ->post(route('crypto.dca.store'), [
            'investment_provider_id' => $purchaseProvider->id,
            'investment_symbol_id' => $symbol->id,
            'purchased_at' => now()->toDateTimeString(),
            'quantity' => '0.10000000',
            'price_per_unit' => '40000.00',
            'fee' => '5.00',
            'add_to_balance' => true,
            'balance_provider_id' => $balanceProvider->id,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('investment_purchases', [
        'investment_provider_id' => $purchaseProvider->id,
        'investment_symbol_id' => $symbol->id,
    ]);
    $this->assertDatabaseHas('crypto_balances', [
        'investment_provider_id' => $balanceProvider->id,
        'investment_symbol_id' => $symbol->id,
        'manual_quantity' => '0.10000000',
    ]);
});

test('creating a crypto dca purchase can increment an existing balance', function () {
    $user = User::factory()->create();
    $provider = InvestmentProvider::factory()->crypto()->create();
    $symbol = InvestmentSymbol::factory()->crypto('ETH')->create();

    CryptoBalance::factory()->create([
        'investment_provider_id' => $provider->id,
        'investment_symbol_id' => $symbol->id,
        'manual_quantity' => '1.25000000',
    ]);

    $this->actingAs($user)
        ->post(route('crypto.dca.store'), [
            'investment_provider_id' => $provider->id,
            'investment_symbol_id' => $symbol->id,
            'purchased_at' => now()->toDateTimeString(),
            'quantity' => '0.75000000',
            'price_per_unit' => '3000.00',
            'fee' => '3.00',
            'add_to_balance' => true,
            'balance_provider_id' => $provider->id,
        ])
        ->assertRedirect();

    expect(CryptoBalance::query()->firstOrFail()->manual_quantity)->toBe('2.00000000');
});

test('adding a crypto dca purchase to balance validates the balance provider', function () {
    $user = User::factory()->create();
    $provider = InvestmentProvider::factory()->crypto()->create();
    $stockProvider = InvestmentProvider::factory()->ilirika()->create();
    $symbol = InvestmentSymbol::factory()->crypto('BTC')->create();

    $payload = [
        'investment_provider_id' => $provider->id,
        'investment_symbol_id' => $symbol->id,
        'purchased_at' => now()->toDateTimeString(),
        'quantity' => '0.10000000',
        'price_per_unit' => '40000.00',
        'fee' => '5.00',
        'add_to_balance' => true,
    ];

    $this->actingAs($user)
        ->post(route('crypto.dca.store'), $payload)
        ->assertSessionHasErrors('balance_provider_id');

    $this->actingAs($user)
        ->post(route('crypto.dca.store'), [
            ...$payload,
            'balance_provider_id' => $stockProvider->id,
        ])
        ->assertSessionHasErrors('balance_provider_id');
});

test('crypto dca purchase rejects non crypto platforms and symbols', function () {
    $user = User::factory()->create();
    $provider = InvestmentProvider::factory()->ilirika()->create();
    $symbol = InvestmentSymbol::factory()->create([
        'type' => InvestmentSymbolType::ETF,
    ]);

    $this->actingAs($user)
        ->post(route('crypto.dca.store'), [
            'investment_provider_id' => $provider->id,
            'investment_symbol_id' => $symbol->id,
            'purchased_at' => now()->toDateTimeString(),
            'quantity' => '1',
            'price_per_unit' => '100.00',
            'fee' => '0.00',
        ])
        ->assertSessionHasErrors([
            'investment_provider_id',
            'investment_symbol_id',
        ]);
});

test('crypto dca update and delete require a crypto purchase', function () {
    $user = User::factory()->create();
    $cryptoProvider = InvestmentProvider::factory()->crypto()->create();
    $cryptoSymbol = InvestmentSymbol::factory()->crypto('ETH')->create();
    $stockProvider = InvestmentProvider::factory()->ibkr()->create();
    $stockSymbol = InvestmentSymbol::factory()->create([
        'type' => InvestmentSymbolType::STOCK,
    ]);
    $stockPurchase = InvestmentPurchase::factory()->create([
        'investment_provider_id' => $stockProvider->id,
        'investment_symbol_id' => $stockSymbol->id,
    ]);

    $payload = [
        'investment_provider_id' => $cryptoProvider->id,
        'investment_symbol_id' => $cryptoSymbol->id,
        'purchased_at' => now()->toDateTimeString(),
        'quantity' => '1',
        'price_per_unit' => '100.00',
        'fee' => '0.00',
    ];

    $this->actingAs($user)
        ->put(route('crypto.dca.update', $stockPurchase), $payload)
        ->assertNotFound();

    $this->actingAs($user)
        ->delete(route('crypto.dca.destroy', $stockPurchase))
        ->assertNotFound();
});
