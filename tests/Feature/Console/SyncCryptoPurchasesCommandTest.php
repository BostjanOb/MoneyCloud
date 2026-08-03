<?php

use App\Enums\BalanceSyncProvider;
use App\Models\InvestmentProvider;
use App\Services\CryptoPurchaseSyncService;
use Illuminate\Console\Scheduling\Schedule;

test('sync crypto purchases command prints the import summary', function () {
    $revolutX = InvestmentProvider::factory()->crypto('revolutx', 'RevolutX')->create([
        'sort_order' => 1,
        'balance_sync_provider' => BalanceSyncProvider::RevolutX->value,
    ]);

    $mock = Mockery::mock(CryptoPurchaseSyncService::class);
    $mock->shouldReceive('syncProvider')
        ->once()
        ->withArgs(fn (InvestmentProvider $provider): bool => $provider->is($revolutX))
        ->andReturn([
            'created_count' => 2,
            'skipped_duplicate' => 1,
            'skipped_currency' => 0,
            'skipped_symbols' => ['SOL'],
        ]);
    app()->instance(CryptoPurchaseSyncService::class, $mock);

    $this->artisan('investments:sync-crypto-purchases')
        ->expectsOutput('RevolutX: uvoženih 2 transakcij, preskočenih duplikatov 1.')
        ->expectsOutput('Skupaj uvoženih: 2. Skupaj preskočenih duplikatov: 1.')
        ->expectsOutput('Manjkajoči simboli: SOL')
        ->assertSuccessful();
});

test('sync crypto purchases command skips providers without api trade history', function () {
    InvestmentProvider::factory()->crypto('binance', 'Binance')->create([
        'balance_sync_provider' => BalanceSyncProvider::Binance->value,
    ]);

    $mock = Mockery::mock(CryptoPurchaseSyncService::class);
    $mock->shouldNotReceive('syncProvider');
    app()->instance(CryptoPurchaseSyncService::class, $mock);

    $this->artisan('investments:sync-crypto-purchases')
        ->expectsOutput('Ni ponudnikov s podprto sinhronizacijo transakcij.')
        ->assertSuccessful();
});

test('sync crypto purchases command reports provider failure and returns failure', function () {
    InvestmentProvider::factory()->crypto('revolutx', 'RevolutX')->create([
        'balance_sync_provider' => BalanceSyncProvider::RevolutX->value,
    ]);

    $mock = Mockery::mock(CryptoPurchaseSyncService::class);
    $mock->shouldReceive('syncProvider')
        ->once()
        ->andThrow(new RuntimeException('Revolut X API error: Unauthorized'));
    app()->instance(CryptoPurchaseSyncService::class, $mock);

    $this->artisan('investments:sync-crypto-purchases')
        ->expectsOutput('RevolutX: Revolut X API error: Unauthorized')
        ->expectsOutput('Skupaj uvoženih: 0. Skupaj preskočenih duplikatov: 0.')
        ->expectsOutput('Neuspešni ponudniki: RevolutX')
        ->assertFailed();
});

test('sync crypto purchases command uses the days option for the sync window', function () {
    InvestmentProvider::factory()->crypto('revolutx', 'RevolutX')->create([
        'balance_sync_provider' => BalanceSyncProvider::RevolutX->value,
    ]);

    $mock = Mockery::mock(CryptoPurchaseSyncService::class);
    $mock->shouldReceive('syncProvider')
        ->once()
        ->withArgs(fn ($provider, $from, $to): bool => (int) round($from->diffInDays($to)) === 30)
        ->andReturn([
            'created_count' => 0,
            'skipped_duplicate' => 0,
            'skipped_currency' => 0,
            'skipped_symbols' => [],
        ]);
    app()->instance(CryptoPurchaseSyncService::class, $mock);

    $this->artisan('investments:sync-crypto-purchases', ['--days' => 30])
        ->expectsOutput('RevolutX: ni novih transakcij.')
        ->assertSuccessful();
});

test('sync crypto purchases command is scheduled weekly on monday at two without overlapping', function () {
    $event = collect(app(Schedule::class)->events())->first(
        fn ($scheduledEvent) => str_contains((string) $scheduledEvent->command, 'investments:sync-crypto-purchases'),
    );

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('0 2 * * 1')
        ->and($event->timezone)->toBe('Europe/Ljubljana')
        ->and($event->withoutOverlapping)->toBeTrue();
});
