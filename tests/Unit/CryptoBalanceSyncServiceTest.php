<?php

use App\Enums\BalanceSyncProvider;
use App\Models\CryptoBalance;
use App\Models\InvestmentProvider;
use App\Models\InvestmentSymbol;
use App\Services\BinanceService;
use App\Services\CryptoBalanceSyncService;
use App\Services\RevolutXService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('it syncs only tracked binance symbols and leaves missing tracked symbols untouched', function () {
    $provider = InvestmentProvider::factory()->crypto('binance', 'Binance')->create([
        'balance_sync_provider' => BalanceSyncProvider::Binance->value,
    ]);
    $btc = InvestmentSymbol::factory()->crypto('BTC')->create();
    $eth = InvestmentSymbol::factory()->crypto('ETH')->create();
    $xrp = InvestmentSymbol::factory()->crypto('XRP')->create();

    $btcBalance = CryptoBalance::factory()->create([
        'investment_provider_id' => $provider->id,
        'investment_symbol_id' => $btc->id,
        'manual_quantity' => '0.10000000',
        'apy' => '6.00',
    ]);
    $ethBalance = CryptoBalance::factory()->create([
        'investment_provider_id' => $provider->id,
        'investment_symbol_id' => $eth->id,
        'manual_quantity' => '1.00000000',
    ]);
    $xrpBalance = CryptoBalance::factory()->create([
        'investment_provider_id' => $provider->id,
        'investment_symbol_id' => $xrp->id,
        'manual_quantity' => '5.00000000',
    ]);

    $mock = Mockery::mock(BinanceService::class);
    $mock->shouldReceive('isConfigured')->andReturnTrue();
    $mock->shouldReceive('getBalanceOverview')->once()->andReturn([
        'BTC' => 0.75,
        'ETH' => 2.5,
        'CRO' => 100,
    ]);
    app()->instance(BinanceService::class, $mock);

    $result = app(CryptoBalanceSyncService::class)->syncProvider($provider);

    expect($result)->toBe([
        'updated_count' => 2,
        'skipped_count' => 1,
    ])
        ->and($btcBalance->fresh()->manual_quantity)->toBe('0.75000000')
        ->and($btcBalance->fresh()->apy)->toBe('6.00')
        ->and($ethBalance->fresh()->manual_quantity)->toBe('2.50000000')
        ->and($xrpBalance->fresh()->manual_quantity)->toBe('5.00000000');
});

test('it syncs revolut x balances and zeroes out assets the exchange reports as empty', function () {
    $provider = InvestmentProvider::factory()->crypto('revolutx', 'Revolut X')->create([
        'balance_sync_provider' => BalanceSyncProvider::RevolutX->value,
    ]);
    $btc = InvestmentSymbol::factory()->crypto('BTC')->create();
    $eth = InvestmentSymbol::factory()->crypto('ETH')->create();
    $xrp = InvestmentSymbol::factory()->crypto('XRP')->create();

    $btcBalance = CryptoBalance::factory()->create([
        'investment_provider_id' => $provider->id,
        'investment_symbol_id' => $btc->id,
        'manual_quantity' => '0.00100000',
    ]);
    $ethBalance = CryptoBalance::factory()->create([
        'investment_provider_id' => $provider->id,
        'investment_symbol_id' => $eth->id,
        'manual_quantity' => '2.00000000',
    ]);
    $xrpBalance = CryptoBalance::factory()->create([
        'investment_provider_id' => $provider->id,
        'investment_symbol_id' => $xrp->id,
        'manual_quantity' => '5.00000000',
    ]);

    $mock = Mockery::mock(RevolutXService::class);
    $mock->shouldReceive('isConfigured')->andReturnTrue();
    $mock->shouldReceive('getBalanceOverview')->once()->andReturn([
        'EUR' => 250.0,
        'BTC' => 0.000546,
        'ETH' => 0.0,
    ]);
    app()->instance(RevolutXService::class, $mock);

    $result = app(CryptoBalanceSyncService::class)->syncProvider($provider);

    expect($result)->toBe([
        'updated_count' => 2,
        'skipped_count' => 1,
    ])
        ->and($btcBalance->fresh()->manual_quantity)->toBe('0.00054600')
        ->and($ethBalance->fresh()->manual_quantity)->toBe('0.00000000')
        ->and($xrpBalance->fresh()->manual_quantity)->toBe('5.00000000');
});

test('it rejects providers without configured balance sync', function () {
    $provider = InvestmentProvider::factory()->crypto()->create();

    expect(fn () => app(CryptoBalanceSyncService::class)->syncProvider($provider))
        ->toThrow(InvalidArgumentException::class, 'Ponudnik nima konfigurirane sinhronizacije kripto stanj.');
});

test('it rejects a sync provider without credentials', function () {
    $provider = InvestmentProvider::factory()->crypto('revolutx', 'Revolut X')->create([
        'balance_sync_provider' => BalanceSyncProvider::RevolutX->value,
    ]);

    $mock = Mockery::mock(RevolutXService::class);
    $mock->shouldReceive('isConfigured')->andReturnFalse();
    $mock->shouldNotReceive('getBalanceOverview');
    app()->instance(RevolutXService::class, $mock);

    expect(fn () => app(CryptoBalanceSyncService::class)->syncProvider($provider))
        ->toThrow(InvalidArgumentException::class, 'Sinhronizacija za Revolut X ni konfigurirana.');
});
