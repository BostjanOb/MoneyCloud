<?php

use App\Services\YfApiInvestmentPriceRefreshService;
use Illuminate\Console\Scheduling\Schedule;

test('refresh yfapi prices command prints the refresh summary', function () {
    $mock = Mockery::mock(YfApiInvestmentPriceRefreshService::class);
    $mock->shouldReceive('refresh')->once()->andReturn([
        'updated_count' => 3,
        'skipped_count' => 0,
        'failed_symbols' => [],
    ]);
    app()->instance(YfApiInvestmentPriceRefreshService::class, $mock);

    $this->artisan('investments:refresh-yfapi-prices')
        ->expectsOutput('Osveženih: 3. Preskočenih: 0.')
        ->assertSuccessful();
});

test('refresh yfapi prices command fails when refresh throws an exception', function () {
    $mock = Mockery::mock(YfApiInvestmentPriceRefreshService::class);
    $mock->shouldReceive('refresh')->once()->andThrow(
        new RuntimeException('YF API ključ ni nastavljen.')
    );
    app()->instance(YfApiInvestmentPriceRefreshService::class, $mock);

    $this->artisan('investments:refresh-yfapi-prices')
        ->expectsOutput('YF API ključ ni nastavljen.')
        ->assertFailed();
});

test('refresh yfapi prices command is scheduled every three hours without overlapping', function () {
    $event = collect(app(Schedule::class)->events())->first(
        fn ($scheduledEvent) => str_contains((string) $scheduledEvent->command, 'investments:refresh-yfapi-prices'),
    );

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('0 */3 * * *')
        ->and($event->timezone)->toBe('Europe/Ljubljana')
        ->and($event->withoutOverlapping)->toBeTrue();
});
