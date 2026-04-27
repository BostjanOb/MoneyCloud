<?php

use App\Enums\BalanceSyncProvider;
use App\Models\InvestmentProvider;
use App\Services\CryptoBalanceSyncService;
use Illuminate\Console\Scheduling\Schedule;

test('sync crypto balances command prints the sync summary', function () {
    $binance = InvestmentProvider::factory()->crypto('binance', 'Binance')->create([
        'sort_order' => 1,
        'balance_sync_provider' => BalanceSyncProvider::Binance->value,
    ]);
    $nexo = InvestmentProvider::factory()->crypto('nexo', 'NEXO')->create([
        'sort_order' => 2,
        'balance_sync_provider' => BalanceSyncProvider::Binance->value,
    ]);

    $mock = Mockery::mock(CryptoBalanceSyncService::class);
    $mock->shouldReceive('syncProvider')
        ->once()
        ->withArgs(fn (InvestmentProvider $provider): bool => $provider->is($binance))
        ->andReturn([
            'updated_count' => 2,
            'skipped_count' => 1,
        ]);
    $mock->shouldReceive('syncProvider')
        ->once()
        ->withArgs(fn (InvestmentProvider $provider): bool => $provider->is($nexo))
        ->andReturn([
            'updated_count' => 1,
            'skipped_count' => 0,
        ]);
    app()->instance(CryptoBalanceSyncService::class, $mock);

    $this->artisan('investments:sync-crypto-balances')
        ->expectsOutput('Binance: sinhroniziranih 2 stanj, preskočenih 1.')
        ->expectsOutput('NEXO: sinhroniziranih 1 stanj, preskočenih 0.')
        ->expectsOutput('Skupaj sinhroniziranih: 3. Skupaj preskočenih: 1.')
        ->assertSuccessful();
});

test('sync crypto balances command continues after provider failure and returns failure', function () {
    $binance = InvestmentProvider::factory()->crypto('binance', 'Binance')->create([
        'sort_order' => 1,
        'balance_sync_provider' => BalanceSyncProvider::Binance->value,
    ]);
    $nexo = InvestmentProvider::factory()->crypto('nexo', 'NEXO')->create([
        'sort_order' => 2,
        'balance_sync_provider' => BalanceSyncProvider::Binance->value,
    ]);

    $mock = Mockery::mock(CryptoBalanceSyncService::class);
    $mock->shouldReceive('syncProvider')
        ->once()
        ->withArgs(fn (InvestmentProvider $provider): bool => $provider->is($binance))
        ->andReturn([
            'updated_count' => 2,
            'skipped_count' => 0,
        ]);
    $mock->shouldReceive('syncProvider')
        ->once()
        ->withArgs(fn (InvestmentProvider $provider): bool => $provider->is($nexo))
        ->andThrow(new RuntimeException('Binance API ključ ni nastavljen.'));
    app()->instance(CryptoBalanceSyncService::class, $mock);

    $this->artisan('investments:sync-crypto-balances')
        ->expectsOutput('Binance: sinhroniziranih 2 stanj, preskočenih 0.')
        ->expectsOutput('NEXO: Binance API ključ ni nastavljen.')
        ->expectsOutput('Skupaj sinhroniziranih: 2. Skupaj preskočenih: 0.')
        ->expectsOutput('Neuspešni ponudniki: NEXO')
        ->assertFailed();
});

test('sync crypto balances command is scheduled every six hours without overlapping', function () {
    $event = collect(app(Schedule::class)->events())->first(
        fn ($scheduledEvent) => str_contains((string) $scheduledEvent->command, 'investments:sync-crypto-balances'),
    );

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('0 */6 * * *')
        ->and($event->timezone)->toBe('Europe/Ljubljana')
        ->and($event->withoutOverlapping)->toBeTrue();
});
