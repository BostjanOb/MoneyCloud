<?php

use App\Models\SavingsAccount;
use App\Models\User;

test('can add interest directly to a leaf account', function () {
    $user = User::factory()->create();
    $account = SavingsAccount::factory()->create([
        'amount' => 100,
    ]);

    $this->actingAs($user)
        ->post(route('savings.interest.store', $account), [
            'amount' => 12.34,
        ])
        ->assertRedirect();

    expect($account->fresh()->amount)->toBe('112.34');
});

test('can distribute interest proportionally across subaccounts and sync parent', function () {
    $user = User::factory()->create();
    $parent = SavingsAccount::factory()->create(['amount' => 0]);
    $firstChild = SavingsAccount::factory()->create([
        'parent_id' => $parent->id,
        'amount' => 100,
    ]);
    $secondChild = SavingsAccount::factory()->create([
        'parent_id' => $parent->id,
        'amount' => 200,
    ]);

    $parent->refresh()->syncAmountFromChildren();

    $this->actingAs($user)
        ->post(route('savings.interest.store', $parent), [
            'amount' => 30,
        ])
        ->assertRedirect();

    expect($firstChild->fresh()->amount)->toBe('110.00')
        ->and($secondChild->fresh()->amount)->toBe('220.00')
        ->and($parent->fresh()->amount)->toBe('330.00');
});

test('distributes evenly when all subaccounts have zero balance', function () {
    $user = User::factory()->create();
    $parent = SavingsAccount::factory()->create(['amount' => 0]);
    $firstChild = SavingsAccount::factory()->create([
        'parent_id' => $parent->id,
        'name' => 'A',
        'amount' => 0,
    ]);
    $secondChild = SavingsAccount::factory()->create([
        'parent_id' => $parent->id,
        'name' => 'B',
        'amount' => 0,
    ]);
    $thirdChild = SavingsAccount::factory()->create([
        'parent_id' => $parent->id,
        'name' => 'C',
        'amount' => 0,
    ]);

    $this->actingAs($user)
        ->post(route('savings.interest.store', $parent), [
            'amount' => 0.05,
        ])
        ->assertRedirect();

    expect($firstChild->fresh()->amount)->toBe('0.01')
        ->and($secondChild->fresh()->amount)->toBe('0.01')
        ->and($thirdChild->fresh()->amount)->toBe('0.03')
        ->and($parent->fresh()->amount)->toBe('0.05');
});

test('adding interest to a child account also updates parent amount', function () {
    $user = User::factory()->create();
    $parent = SavingsAccount::factory()->create(['amount' => 0]);
    $child = SavingsAccount::factory()->create([
        'parent_id' => $parent->id,
        'amount' => 50,
    ]);

    $parent->refresh()->syncAmountFromChildren();

    $this->actingAs($user)
        ->post(route('savings.interest.store', $child), [
            'amount' => 1.25,
        ])
        ->assertRedirect();

    expect($child->fresh()->amount)->toBe('51.25')
        ->and($parent->fresh()->amount)->toBe('51.25');
});
