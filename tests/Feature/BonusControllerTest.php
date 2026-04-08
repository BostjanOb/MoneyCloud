<?php

use App\Models\Bonus;
use App\Models\PaycheckYear;
use App\Models\User;

test('can store a bonus', function () {
    $user = User::factory()->create();
    $year = PaycheckYear::factory()->create();

    $this->actingAs($user)
        ->post(route('place.bonus.store'), [
            'paycheck_year_id' => $year->id,
            'type' => 'regres',
            'amount' => 940.58,
            'description' => null,
            'paid_at' => null,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('paycheck_bonuses', [
        'paycheck_year_id' => $year->id,
        'type' => 'regres',
    ]);
});

test('can delete a bonus', function () {
    $user = User::factory()->create();
    $bonus = Bonus::factory()->create();

    $this->actingAs($user)
        ->delete(route('place.bonus.destroy', $bonus))
        ->assertRedirect();

    $this->assertDatabaseMissing('paycheck_bonuses', ['id' => $bonus->id]);
});

test('validates bonus store request', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('place.bonus.store'), [])
        ->assertSessionHasErrors(['paycheck_year_id', 'type', 'amount']);
});
