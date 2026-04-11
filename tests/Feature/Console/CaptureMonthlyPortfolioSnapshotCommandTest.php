<?php

use App\Contracts\InvestmentPriceRefreshService;
use App\Models\MonthlyPortfolioSnapshot;
use App\Models\SavingsAccount;

test('capture command refreshes prices before saving snapshot', function () {
    $state = (object) [
        'refreshed' => false,
        'refreshed_before_create' => false,
    ];

    app()->instance(InvestmentPriceRefreshService::class, new class($state) implements InvestmentPriceRefreshService
    {
        public function __construct(private object $state) {}

        public function refresh(): array
        {
            $this->state->refreshed = true;

            return [
                'updated_count' => 0,
                'skipped_count' => 0,
                'failed_symbols' => [],
            ];
        }
    });

    MonthlyPortfolioSnapshot::creating(function () use ($state): void {
        $state->refreshed_before_create = $state->refreshed;
    });

    $this->artisan('statistics:capture-monthly-portfolio-snapshot --month=2026-04')
        ->assertSuccessful();

    expect($state->refreshed)->toBeTrue()
        ->and($state->refreshed_before_create)->toBeTrue()
        ->and(MonthlyPortfolioSnapshot::query()->count())->toBe(1);
});

test('capture command upserts the same month instead of creating duplicates', function () {
    SavingsAccount::factory()->create([
        'amount' => '100.00',
    ]);

    $this->artisan('statistics:capture-monthly-portfolio-snapshot --month=2026-04')
        ->assertSuccessful();

    SavingsAccount::query()->update([
        'amount' => '300.00',
    ]);

    $this->artisan('statistics:capture-monthly-portfolio-snapshot --month=2026-04')
        ->assertSuccessful();

    expect(MonthlyPortfolioSnapshot::query()->count())->toBe(1)
        ->and(MonthlyPortfolioSnapshot::query()->sole()->month_date?->toDateString())->toBe('2026-04-01')
        ->and(MonthlyPortfolioSnapshot::query()->sole()->total_amount)->toBe('300.00')
        ->and(MonthlyPortfolioSnapshot::query()->sole()->source)->toBe(MonthlyPortfolioSnapshot::SOURCE_SCHEDULED);
});
