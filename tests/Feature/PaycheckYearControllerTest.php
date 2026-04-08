<?php

use App\Models\PaycheckYear;
use App\Models\User;

test('can create a new paycheck year', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('place.year.store'), [
            'employee' => 'bostjan',
            'year' => 2026,
            'child1_months' => 12,
            'child2_months' => 12,
            'child3_months' => 0,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('paycheck_years', [
        'employee' => 'bostjan',
        'year' => 2026,
    ]);
});

test('can update paycheck year child months', function () {
    $user = User::factory()->create();
    $year = PaycheckYear::factory()->create([
        'child1_months' => 12,
        'child2_months' => 0,
        'child3_months' => 0,
    ]);

    $this->actingAs($user)
        ->put(route('place.year.update', $year), [
            'child1_months' => 6,
            'child2_months' => 3,
            'child3_months' => 0,
        ])
        ->assertRedirect();

    expect($year->fresh()->child1_months)->toBe(6);
    expect($year->fresh()->child2_months)->toBe(3);
});

test('validates employee enum', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('place.year.store'), [
            'employee' => 'invalid',
            'year' => 2026,
            'child1_months' => 12,
            'child2_months' => 12,
            'child3_months' => 0,
        ])
        ->assertSessionHasErrors('employee');
});

test('validates child months range', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->put(route('place.year.update', PaycheckYear::factory()->create()), [
            'child1_months' => 15,
            'child2_months' => 0,
            'child3_months' => 0,
        ])
        ->assertSessionHasErrors('child1_months');
});
