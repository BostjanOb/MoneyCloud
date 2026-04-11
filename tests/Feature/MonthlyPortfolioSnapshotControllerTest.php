<?php

use App\Models\MonthlyPortfolioSnapshot;
use App\Models\User;

test('authenticated user can store a manual monthly snapshot and total is recalculated', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('statistics.monthly-snapshots.store'), [
            'month_date' => '2025-04',
            'savings_amount' => '1000.00',
            'bond_amount' => '200.00',
            'etf_amount' => '300.00',
            'crypto_amount' => '400.00',
            'stock_amount' => '500.00',
        ])
        ->assertRedirect();

    $snapshot = MonthlyPortfolioSnapshot::query()->sole();

    expect($snapshot->month_date?->toDateString())->toBe('2025-04-01')
        ->and($snapshot->total_amount)->toBe('2400.00')
        ->and($snapshot->source)->toBe(MonthlyPortfolioSnapshot::SOURCE_MANUAL);
});

test('storing a manual monthly snapshot validates unique month', function () {
    $user = User::factory()->create();
    MonthlyPortfolioSnapshot::factory()->create([
        'month_date' => '2025-04-01',
    ]);

    $this->actingAs($user)
        ->post(route('statistics.monthly-snapshots.store'), [
            'month_date' => '2025-04',
            'savings_amount' => '1000.00',
            'bond_amount' => '0.00',
            'etf_amount' => '0.00',
            'crypto_amount' => '0.00',
            'stock_amount' => '0.00',
        ])
        ->assertSessionHasErrors('month_date');
});

test('updating a monthly snapshot recalculates total and marks it as manual', function () {
    $user = User::factory()->create();
    $snapshot = MonthlyPortfolioSnapshot::factory()->create([
        'month_date' => '2025-04-01',
        'total_amount' => '100.00',
        'source' => MonthlyPortfolioSnapshot::SOURCE_SCHEDULED,
    ]);

    $this->actingAs($user)
        ->put(route('statistics.monthly-snapshots.update', $snapshot), [
            'month_date' => '2025-04',
            'savings_amount' => '100.00',
            'bond_amount' => '100.00',
            'etf_amount' => '100.00',
            'crypto_amount' => '100.00',
            'stock_amount' => '100.00',
        ])
        ->assertRedirect();

    expect($snapshot->fresh()->total_amount)->toBe('500.00')
        ->and($snapshot->fresh()->source)->toBe(MonthlyPortfolioSnapshot::SOURCE_MANUAL);
});
