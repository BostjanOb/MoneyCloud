<?php

use App\Enums\BonusType;
use App\Models\Bonus;
use App\Models\PaycheckYear;
use App\Models\User;

test('can store a bonus', function () {
    $user = User::factory()->create();
    $year = PaycheckYear::factory()->create();

    $this->actingAs($user)
        ->post(route('place.bonus.store'), [
            'paycheck_year_id' => $year->id,
            'type' => BonusType::REGRES->value,
            'amount' => 940.58,
            'taxable' => false,
            'paid_tax' => 0,
            'description' => null,
            'paid_at' => null,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('paycheck_bonuses', [
        'paycheck_year_id' => $year->id,
        'type' => BonusType::REGRES->value,
        'taxable' => false,
        'paid_tax' => 0,
    ]);
});

test('can store a taxable bonus', function () {
    $user = User::factory()->create();
    $year = PaycheckYear::factory()->create();

    $this->actingAs($user)
        ->post(route('place.bonus.store'), [
            'paycheck_year_id' => $year->id,
            'type' => BonusType::SP->value,
            'amount' => 1500,
            'taxable' => true,
            'paid_tax' => 375.50,
            'description' => 'Obdavčljiv bonus',
            'paid_at' => '2025-12-10',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('paycheck_bonuses', [
        'paycheck_year_id' => $year->id,
        'type' => BonusType::SP->value,
        'taxable' => true,
        'paid_tax' => 375.50,
    ]);
});

test('can update a bonus', function () {
    $user = User::factory()->create();
    $bonus = Bonus::factory()->create([
        'type' => BonusType::REGRES->value,
        'amount' => 900,
        'taxable' => false,
        'paid_tax' => 0,
    ]);

    $this->actingAs($user)
        ->put(route('place.bonus.update', $bonus), [
            'type' => BonusType::SP->value,
            'amount' => 1250.45,
            'taxable' => true,
            'paid_tax' => 220.10,
            'description' => 'Popravljen bonus',
            'paid_at' => '2025-12-15',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('paycheck_bonuses', [
        'id' => $bonus->id,
        'type' => BonusType::SP->value,
        'amount' => 1250.45,
        'taxable' => true,
        'paid_tax' => 220.10,
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

test('validates bonus type enum', function () {
    $user = User::factory()->create();
    $year = PaycheckYear::factory()->create();

    $this->actingAs($user)
        ->post(route('place.bonus.store'), [
            'paycheck_year_id' => $year->id,
            'type' => 'invalid',
            'amount' => 100,
            'taxable' => false,
        ])
        ->assertSessionHasErrors(['type']);
});

test('requires paid tax for taxable bonuses', function () {
    $user = User::factory()->create();
    $year = PaycheckYear::factory()->create();

    $this->actingAs($user)
        ->post(route('place.bonus.store'), [
            'paycheck_year_id' => $year->id,
            'type' => BonusType::BONI_MALICA->value,
            'amount' => 200,
            'taxable' => true,
        ])
        ->assertSessionHasErrors(['paid_tax']);
});
