<?php

use App\Contracts\InvestmentPriceRefreshService;
use Illuminate\Console\Scheduling\Schedule;

test('refresh crypto prices command prints the refresh summary', function () {
    app()->instance(InvestmentPriceRefreshService::class, new class implements InvestmentPriceRefreshService
    {
        public function refresh(): array
        {
            return [
                'updated_count' => 2,
                'skipped_count' => 1,
                'failed_symbols' => ['CRO'],
            ];
        }
    });

    $this->artisan('investments:refresh-crypto-prices')
        ->expectsOutput('Osveženih: 2. Preskočenih: 1.')
        ->expectsOutput('Neuspešni simboli: CRO')
        ->assertSuccessful();
});

test('refresh crypto prices command fails when refresh throws an exception', function () {
    app()->instance(InvestmentPriceRefreshService::class, new class implements InvestmentPriceRefreshService
    {
        public function refresh(): array
        {
            throw new RuntimeException('CoinMarketCap API ključ ni nastavljen.');
        }
    });

    $this->artisan('investments:refresh-crypto-prices')
        ->expectsOutput('CoinMarketCap API ključ ni nastavljen.')
        ->assertFailed();
});

test('refresh crypto prices command is scheduled every three hours without overlapping', function () {
    $event = collect(app(Schedule::class)->events())->first(
        fn ($scheduledEvent) => str_contains((string) $scheduledEvent->command, 'investments:refresh-crypto-prices'),
    );

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('0 */3 * * *')
        ->and($event->timezone)->toBe('Europe/Ljubljana')
        ->and($event->withoutOverlapping)->toBeTrue();
});
