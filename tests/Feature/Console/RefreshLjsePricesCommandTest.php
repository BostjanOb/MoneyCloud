<?php

use App\Services\LjseInvestmentPriceRefreshService;
use Illuminate\Console\Scheduling\Schedule;

test('refresh ljse prices command prints the refresh summary', function () {
    $mock = Mockery::mock(LjseInvestmentPriceRefreshService::class);
    $mock->shouldReceive('refresh')->once()->andReturn([
        'updated_count' => 3,
        'skipped_count' => 1,
        'failed_symbols' => ['RS96'],
    ]);
    app()->instance(LjseInvestmentPriceRefreshService::class, $mock);

    $this->artisan('investments:refresh-ljse-prices')
        ->expectsOutput('Osveženih: 3. Preskočenih: 1.')
        ->expectsOutput('Neuspešni simboli: RS96')
        ->assertSuccessful();
});

test('refresh ljse prices command fails when refresh throws an exception', function () {
    $mock = Mockery::mock(LjseInvestmentPriceRefreshService::class);
    $mock->shouldReceive('refresh')->once()->andThrow(
        new RuntimeException('Povezava do LJSE ni uspela.')
    );
    app()->instance(LjseInvestmentPriceRefreshService::class, $mock);

    $this->artisan('investments:refresh-ljse-prices')
        ->expectsOutput('Povezava do LJSE ni uspela.')
        ->assertFailed();
});

test('refresh ljse prices command is scheduled every three hours without overlapping', function () {
    $event = collect(app(Schedule::class)->events())->first(
        fn ($scheduledEvent) => str_contains((string) $scheduledEvent->command, 'investments:refresh-ljse-prices'),
    );

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('0 */3 * * *')
        ->and($event->timezone)->toBe('Europe/Ljubljana')
        ->and($event->withoutOverlapping)->toBeTrue();
});
