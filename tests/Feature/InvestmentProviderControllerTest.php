<?php

use App\Enums\InvestmentSymbolType;
use App\Models\InvestmentProvider;
use App\Models\InvestmentPurchase;
use App\Models\InvestmentSymbol;
use App\Models\SavingsAccount;
use App\Models\User;

beforeEach(function () {
    InvestmentProvider::factory()->ibkr()->create();
    InvestmentProvider::factory()->ilirika()->create();
});

test('investment provider page requires authentication', function () {
    InvestmentProvider::query()->firstWhere('slug', 'ibkr');

    $this->get(route('investments.providers.show', 'ibkr'))
        ->assertRedirect(route('login'));
});

test('authenticated user can view provider pages with summary and filtered symbols', function () {
    $user = User::factory()->create();
    $ibkr = InvestmentProvider::query()->firstWhere('slug', 'ibkr');
    $ilirika = InvestmentProvider::query()->firstWhere('slug', 'ilirika');

    $stock = InvestmentSymbol::factory()->create([
        'type' => InvestmentSymbolType::STOCK,
        'symbol' => 'AAPL',
        'current_price' => '120.00',
        'taxable' => true,
    ]);
    $etf = InvestmentSymbol::factory()->create([
        'type' => InvestmentSymbolType::ETF,
        'symbol' => 'SXR8',
        'current_price' => '90.00',
        'taxable' => false,
    ]);
    $bond = InvestmentSymbol::factory()->bond()->create([
        'symbol' => 'SI123',
        'current_price' => '105.00',
    ]);

    InvestmentPurchase::factory()->create([
        'investment_provider_id' => $ibkr->id,
        'investment_symbol_id' => $stock->id,
        'purchased_at' => now()->subYears(3),
        'quantity' => '2.00000000',
        'price_per_unit' => '100.00',
        'fee' => '10.00',
    ]);

    InvestmentPurchase::factory()->create([
        'investment_provider_id' => $ibkr->id,
        'investment_symbol_id' => $stock->id,
        'purchased_at' => now()->subYears(3),
        'quantity' => '1.00000000',
        'price_per_unit' => '80.00',
        'fee' => '5.00',
    ]);

    InvestmentPurchase::factory()->create([
        'investment_provider_id' => $ibkr->id,
        'investment_symbol_id' => $etf->id,
        'purchased_at' => now()->subYears(1),
        'quantity' => '2.00000000',
        'price_per_unit' => '85.00',
        'fee' => '3.00',
    ]);

    $this->actingAs($user)
        ->get(route('investments.providers.show', 'ibkr'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Investicije/Provider')
            ->where('provider.slug', 'ibkr')
            ->has('investmentProviders', 2)
            ->where('investmentProviders.0.slug', 'ibkr')
            ->where('investmentProviders.0.name', 'IBKR')
            ->where('investmentProviders.1.slug', 'ilirika')
            ->where('investmentProviders.1.name', 'Ilirika')
            ->where('summary.total_invested', '450.00')
            ->where('summary.current_value', '540.00')
            ->where('summary.profit_loss', '72.00')
            ->where('summary.profit_loss_after_tax', '53.60')
            ->has('symbolSummary', 2)
            ->where('symbolSummary.0.symbol', 'AAPL')
            ->where('symbolSummary.0.current_value', '360.00')
            ->where('symbolSummary.0.return_percentage', '23.21')
            ->where('symbolSummary.0.quantity', '3.00000000')
            ->where('symbolSummary.0.total_invested', '280.00')
            ->where('symbolSummary.0.profit_loss', '65.00')
            ->where('symbolSummary.1.symbol', 'SXR8')
            ->where('symbolSummary.1.current_value', '180.00')
            ->where('symbolSummary.1.return_percentage', '4.12')
            ->where('symbolSummary.1.quantity', '2.00000000')
            ->where('symbolSummary.1.total_invested', '170.00')
            ->where('symbolSummary.1.profit_loss', '7.00')
            ->has('purchases', 3)
            ->has('symbolOptions', 2)
            ->where('symbolOptions.0.symbol', 'AAPL')
            ->where('symbolOptions.1.symbol', 'SXR8')
        );

    $this->actingAs($user)
        ->get(route('investments.providers.show', 'ilirika'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Investicije/Provider')
            ->where('provider.slug', 'ilirika')
            ->has('symbolOptions', 1)
            ->where('symbolOptions.0.symbol', $bond->symbol)
        );
});

test('provider supported symbol types are database driven', function () {
    $user = User::factory()->create();
    $provider = InvestmentProvider::query()->firstWhere('slug', 'ibkr');
    $provider->update([
        'supported_symbol_types' => [InvestmentSymbolType::BOND->value],
    ]);

    $stock = InvestmentSymbol::factory()->create([
        'type' => InvestmentSymbolType::STOCK,
        'symbol' => 'AAPL',
    ]);
    $bond = InvestmentSymbol::factory()->bond()->create([
        'symbol' => 'SI123',
    ]);

    $this->actingAs($user)
        ->get(route('investments.providers.show', $provider->slug))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Investicije/Provider')
            ->has('symbolOptions', 1)
            ->where('symbolOptions.0.symbol', $bond->symbol)
        );

    $this->actingAs($user)
        ->post(route('investments.purchases.store', $provider->slug), [
            'investment_symbol_id' => $stock->id,
            'purchased_at' => now()->toDateTimeString(),
            'quantity' => '1',
            'price_per_unit' => '100.00',
            'fee' => '0.00',
        ])
        ->assertSessionHasErrors('investment_symbol_id');
});

test('provider linked savings account requirement is database driven', function () {
    $user = User::factory()->create();
    $provider = InvestmentProvider::factory()->create([
        'slug' => 'nova',
        'name' => 'Nova',
        'requires_linked_savings_account' => true,
        'supported_symbol_types' => [InvestmentSymbolType::ETF->value],
    ]);
    $linkedAccount = SavingsAccount::factory()->create([
        'owner' => 'bostjan',
        'amount' => '1000.00',
    ]);
    $symbol = InvestmentSymbol::factory()->create([
        'type' => InvestmentSymbolType::ETF,
    ]);

    $payload = [
        'investment_symbol_id' => $symbol->id,
        'purchased_at' => now()->toDateTimeString(),
        'quantity' => '2',
        'price_per_unit' => '100.00',
        'fee' => '5.00',
    ];

    $this->actingAs($user)
        ->post(route('investments.purchases.store', $provider->slug), $payload)
        ->assertSessionHasErrors('investment_symbol_id');

    $provider->update([
        'linked_savings_account_id' => $linkedAccount->id,
    ]);

    $this->actingAs($user)
        ->post(route('investments.purchases.store', $provider->slug), $payload)
        ->assertRedirect();

    expect($linkedAccount->fresh()->amount)->toBe('795.00');
});

test('creating an ibkr purchase decreases the linked savings balance', function () {
    $user = User::factory()->create();
    $linkedAccount = SavingsAccount::factory()->create([
        'owner' => 'bostjan',
        'amount' => '1000.00',
    ]);
    $provider = InvestmentProvider::query()->firstWhere('slug', 'ibkr');
    $provider->update([
        'linked_savings_account_id' => $linkedAccount->id,
    ]);
    $symbol = InvestmentSymbol::factory()->create([
        'type' => InvestmentSymbolType::ETF,
    ]);

    $this->actingAs($user)
        ->post(route('investments.purchases.store', $provider->slug), [
            'investment_symbol_id' => $symbol->id,
            'purchased_at' => now()->toDateTimeString(),
            'quantity' => '2',
            'price_per_unit' => '100.00',
            'fee' => '5.00',
        ])
        ->assertRedirect();

    expect($linkedAccount->fresh()->amount)->toBe('795.00');

    $this->assertDatabaseHas('investment_purchases', [
        'investment_provider_id' => $provider->id,
        'investment_symbol_id' => $symbol->id,
    ]);
});

test('updating an ibkr purchase adjusts the linked savings balance by the delta', function () {
    $user = User::factory()->create();
    $linkedAccount = SavingsAccount::factory()->create([
        'owner' => 'bostjan',
        'amount' => '1000.00',
    ]);
    $provider = InvestmentProvider::query()->firstWhere('slug', 'ibkr');
    $provider->update([
        'linked_savings_account_id' => $linkedAccount->id,
    ]);
    $symbol = InvestmentSymbol::factory()->create([
        'type' => InvestmentSymbolType::STOCK,
    ]);

    $this->actingAs($user)->post(route('investments.purchases.store', $provider->slug), [
        'investment_symbol_id' => $symbol->id,
        'purchased_at' => now()->toDateTimeString(),
        'quantity' => '2',
        'price_per_unit' => '100.00',
        'fee' => '5.00',
    ]);

    $purchase = InvestmentPurchase::query()->firstOrFail();

    $this->actingAs($user)
        ->put(route('investments.purchases.update', [
            'investmentProvider' => $provider->slug,
            'investmentPurchase' => $purchase->id,
        ]), [
            'investment_symbol_id' => $symbol->id,
            'purchased_at' => now()->toDateTimeString(),
            'quantity' => '3',
            'price_per_unit' => '100.00',
            'fee' => '5.00',
        ])
        ->assertRedirect();

    expect($linkedAccount->fresh()->amount)->toBe('695.00');
});

test('deleting an ibkr purchase refunds the linked savings balance', function () {
    $user = User::factory()->create();
    $linkedAccount = SavingsAccount::factory()->create([
        'owner' => 'bostjan',
        'amount' => '1000.00',
    ]);
    $provider = InvestmentProvider::query()->firstWhere('slug', 'ibkr');
    $provider->update([
        'linked_savings_account_id' => $linkedAccount->id,
    ]);
    $symbol = InvestmentSymbol::factory()->create([
        'type' => InvestmentSymbolType::ETF,
    ]);

    $this->actingAs($user)->post(route('investments.purchases.store', $provider->slug), [
        'investment_symbol_id' => $symbol->id,
        'purchased_at' => now()->toDateTimeString(),
        'quantity' => '2',
        'price_per_unit' => '100.00',
        'fee' => '5.00',
    ]);

    $purchase = InvestmentPurchase::query()->firstOrFail();

    $this->actingAs($user)
        ->delete(route('investments.purchases.destroy', [
            'investmentProvider' => $provider->slug,
            'investmentPurchase' => $purchase->id,
        ]))
        ->assertRedirect();

    expect($linkedAccount->fresh()->amount)->toBe('1000.00');
    $this->assertDatabaseMissing('investment_purchases', ['id' => $purchase->id]);
});

test('ibkr purchase cannot exceed linked savings balance', function () {
    $user = User::factory()->create();
    $linkedAccount = SavingsAccount::factory()->create([
        'owner' => 'bostjan',
        'amount' => '100.00',
    ]);
    $provider = InvestmentProvider::query()->firstWhere('slug', 'ibkr');
    $provider->update([
        'linked_savings_account_id' => $linkedAccount->id,
    ]);
    $symbol = InvestmentSymbol::factory()->create([
        'type' => InvestmentSymbolType::ETF,
    ]);

    $this->actingAs($user)
        ->post(route('investments.purchases.store', $provider->slug), [
            'investment_symbol_id' => $symbol->id,
            'purchased_at' => now()->toDateTimeString(),
            'quantity' => '2',
            'price_per_unit' => '100.00',
            'fee' => '5.00',
        ])
        ->assertSessionHasErrors('quantity');

    expect($linkedAccount->fresh()->amount)->toBe('100.00');
    $this->assertDatabaseCount('investment_purchases', 0);
});

test('bond purchases require bond specific fields', function () {
    $user = User::factory()->create();
    $provider = InvestmentProvider::query()->firstWhere('slug', 'ilirika');
    $bond = InvestmentSymbol::factory()->bond()->create();

    $this->actingAs($user)
        ->post(route('investments.purchases.store', $provider->slug), [
            'investment_symbol_id' => $bond->id,
            'purchased_at' => now()->toDateTimeString(),
            'quantity' => '5',
            'price_per_unit' => '99.50',
            'fee' => '2.00',
        ])
        ->assertSessionHasErrors(['yield', 'coupon_date', 'expiry_date']);
});

test('generic investment pages hide crypto only providers and crypto purchases', function () {
    $user = User::factory()->create();
    $provider = InvestmentProvider::query()->firstWhere('slug', 'ibkr');
    InvestmentProvider::factory()->crypto('nexo', 'NEXO')->create();
    $stock = InvestmentSymbol::factory()->create([
        'type' => InvestmentSymbolType::STOCK,
        'symbol' => 'AAPL',
        'current_price' => '120.00',
    ]);
    $crypto = InvestmentSymbol::factory()->crypto('BTC')->create([
        'current_price' => '50000.00',
    ]);

    InvestmentPurchase::factory()->create([
        'investment_provider_id' => $provider->id,
        'investment_symbol_id' => $stock->id,
        'quantity' => '1.00000000',
        'price_per_unit' => '100.00',
        'fee' => '0.00',
    ]);
    InvestmentPurchase::factory()->create([
        'investment_provider_id' => $provider->id,
        'investment_symbol_id' => $crypto->id,
        'quantity' => '1.00000000',
        'price_per_unit' => '40000.00',
        'fee' => '0.00',
    ]);

    $this->actingAs($user)
        ->get(route('investments.providers.show', $provider->slug))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Investicije/Provider')
            ->has('investmentProviders', 2)
            ->where('investmentProviders.0.slug', 'ibkr')
            ->where('investmentProviders.1.slug', 'ilirika')
            ->where('summary.total_invested', '100.00')
            ->has('purchases', 1)
            ->where('purchases.0.symbol.symbol', 'AAPL')
            ->has('symbolOptions', 1)
            ->where('symbolOptions.0.symbol', 'AAPL')
        );
});
