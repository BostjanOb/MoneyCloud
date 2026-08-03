<?php

use App\Enums\BalanceSyncProvider;
use App\Models\CryptoBalance;
use App\Models\InvestmentProvider;
use App\Models\InvestmentPurchase;
use App\Models\InvestmentSymbol;
use App\Services\CryptoPurchaseSyncService;
use App\Services\RevolutXService;
use Carbon\CarbonImmutable;

function revolutXOrder(array $overrides = []): array
{
    return array_merge([
        'external_id' => '8d179909-bbed-4487-9d65-5b8c9ec48514',
        'base_asset' => 'BTC',
        'quote_asset' => 'EUR',
        'side' => 'buy',
        'executed_at' => '2026-08-02 22:02:45',
        'quantity' => '0.00054600',
        'price_per_unit' => '54895.020',
        'fee' => '0.03',
    ], $overrides);
}

function revolutXProvider(): InvestmentProvider
{
    return InvestmentProvider::factory()->crypto('revolutx', 'RevolutX')->create([
        'balance_sync_provider' => BalanceSyncProvider::RevolutX->value,
    ]);
}

function mockRevolutXOrders(array $orders): void
{
    $mock = Mockery::mock(RevolutXService::class);
    $mock->shouldReceive('isConfigured')->andReturnTrue();
    $mock->shouldReceive('getFilledOrders')->once()->andReturn($orders);
    app()->instance(RevolutXService::class, $mock);
}

function syncRevolutXPurchases(InvestmentProvider $provider): array
{
    $to = CarbonImmutable::parse('2026-08-03 10:00:00');

    return app(CryptoPurchaseSyncService::class)->syncProvider($provider, $to->subDays(14), $to);
}

test('it imports filled orders as investment purchases', function () {
    $provider = revolutXProvider();
    $btc = InvestmentSymbol::factory()->crypto('BTC')->create();
    $eth = InvestmentSymbol::factory()->crypto('ETH')->create();

    mockRevolutXOrders([
        revolutXOrder(),
        revolutXOrder([
            'external_id' => '692d5ea6-55fd-4823-863d-29ce869454d2',
            'base_asset' => 'ETH',
            'quantity' => '0.01227245',
            'price_per_unit' => '1628.200',
            'fee' => '0.02',
        ]),
    ]);

    $result = syncRevolutXPurchases($provider);

    expect($result)->toBe([
        'created_count' => 2,
        'skipped_duplicate' => 0,
        'skipped_currency' => 0,
        'skipped_symbols' => [],
    ]);

    $this->assertDatabaseHas('investment_purchases', [
        'investment_provider_id' => $provider->id,
        'investment_symbol_id' => $btc->id,
        'external_id' => '8d179909-bbed-4487-9d65-5b8c9ec48514',
        'transaction_type' => 'buy',
        'quantity' => '0.00054600',
        'price_per_unit' => '54895.020',
        'fee' => '0.03',
    ]);

    $ethPurchase = InvestmentPurchase::where('investment_symbol_id', $eth->id)->sole();

    expect($ethPurchase->purchased_at->format('Y-m-d H:i:s'))->toBe('2026-08-02 22:02:45')
        ->and($ethPurchase->quantity)->toBe('0.01227245');
});

test('it skips orders that were already imported', function () {
    $provider = revolutXProvider();
    InvestmentSymbol::factory()->crypto('BTC')->create();

    mockRevolutXOrders([revolutXOrder()]);
    syncRevolutXPurchases($provider);

    mockRevolutXOrders([revolutXOrder()]);
    $result = syncRevolutXPurchases($provider);

    expect($result['created_count'])->toBe(0)
        ->and($result['skipped_duplicate'])->toBe(1)
        ->and(InvestmentPurchase::count())->toBe(1);
});

test('it imports sells as sell transactions', function () {
    $provider = revolutXProvider();
    InvestmentSymbol::factory()->crypto('BTC')->create();

    mockRevolutXOrders([revolutXOrder(['side' => 'sell'])]);

    expect(syncRevolutXPurchases($provider)['created_count'])->toBe(1);

    $this->assertDatabaseHas('investment_purchases', [
        'external_id' => '8d179909-bbed-4487-9d65-5b8c9ec48514',
        'transaction_type' => 'sell',
    ]);
});

test('it skips non eur pairs and reports unknown symbols', function () {
    $provider = revolutXProvider();
    InvestmentSymbol::factory()->crypto('BTC')->create();

    mockRevolutXOrders([
        revolutXOrder(['external_id' => 'usd-order', 'quote_asset' => 'USD']),
        revolutXOrder(['external_id' => 'sol-order', 'base_asset' => 'SOL']),
        revolutXOrder(['external_id' => 'sol-order-2', 'base_asset' => 'SOL']),
        revolutXOrder(),
    ]);

    expect(syncRevolutXPurchases($provider))->toBe([
        'created_count' => 1,
        'skipped_duplicate' => 0,
        'skipped_currency' => 1,
        'skipped_symbols' => ['SOL'],
    ]);
});

test('it leaves crypto balances untouched', function () {
    $provider = revolutXProvider();
    $btc = InvestmentSymbol::factory()->crypto('BTC')->create();
    $balance = CryptoBalance::factory()->create([
        'investment_provider_id' => $provider->id,
        'investment_symbol_id' => $btc->id,
        'manual_quantity' => '1.00000000',
    ]);

    mockRevolutXOrders([revolutXOrder()]);
    syncRevolutXPurchases($provider);

    expect($balance->fresh()->manual_quantity)->toBe('1.00000000');
});

test('it rejects providers whose exchange has no trade history api', function () {
    $provider = InvestmentProvider::factory()->crypto('binance', 'Binance')->create([
        'balance_sync_provider' => BalanceSyncProvider::Binance->value,
    ]);

    expect(fn () => syncRevolutXPurchases($provider))
        ->toThrow(InvalidArgumentException::class, 'Binance ne podpira sinhronizacije transakcij.');
});

test('it rejects providers without configured credentials', function () {
    $provider = revolutXProvider();

    $mock = Mockery::mock(RevolutXService::class);
    $mock->shouldReceive('isConfigured')->andReturnFalse();
    $mock->shouldNotReceive('getFilledOrders');
    app()->instance(RevolutXService::class, $mock);

    expect(fn () => syncRevolutXPurchases($provider))
        ->toThrow(InvalidArgumentException::class, 'Sinhronizacija za Revolut X ni konfigurirana.');
});
