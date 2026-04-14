<?php

use App\Models\SavingsAccount;
use App\Models\User;

test('direct add increases a leaf account balance', function () {
    $user = User::factory()->create();
    $account = SavingsAccount::factory()->create([
        'amount' => 100,
    ]);

    $this->actingAs($user)
        ->post(route('savings.balance-adjustments.store', $account), [
            'operation' => 'add',
            'amount' => 25.50,
        ])
        ->assertRedirect();

    expect($account->fresh()->amount)->toBe('125.50');
});

test('direct subtract decreases a leaf account balance', function () {
    $user = User::factory()->create();
    $account = SavingsAccount::factory()->create([
        'amount' => 100,
    ]);

    $this->actingAs($user)
        ->post(route('savings.balance-adjustments.store', $account), [
            'operation' => 'subtract',
            'amount' => 24.25,
        ])
        ->assertRedirect();

    expect($account->fresh()->amount)->toBe('75.75');
});

test('add with related account transfers from related account to target account', function () {
    $user = User::factory()->create();
    $targetAccount = SavingsAccount::factory()->create([
        'name' => 'Target',
        'amount' => 100,
    ]);
    $sourceAccount = SavingsAccount::factory()->create([
        'name' => 'Source',
        'amount' => 60,
    ]);

    $this->actingAs($user)
        ->post(route('savings.balance-adjustments.store', $targetAccount), [
            'operation' => 'add',
            'amount' => 25,
            'related_account_id' => $sourceAccount->id,
        ])
        ->assertRedirect();

    expect($targetAccount->fresh()->amount)->toBe('125.00')
        ->and($sourceAccount->fresh()->amount)->toBe('35.00');
});

test('subtract with related account transfers from target account to related account', function () {
    $user = User::factory()->create();
    $targetAccount = SavingsAccount::factory()->create([
        'name' => 'Target',
        'amount' => 100,
    ]);
    $destinationAccount = SavingsAccount::factory()->create([
        'name' => 'Destination',
        'amount' => 60,
    ]);

    $this->actingAs($user)
        ->post(route('savings.balance-adjustments.store', $targetAccount), [
            'operation' => 'subtract',
            'amount' => 25,
            'related_account_id' => $destinationAccount->id,
        ])
        ->assertRedirect();

    expect($targetAccount->fresh()->amount)->toBe('75.00')
        ->and($destinationAccount->fresh()->amount)->toBe('85.00');
});

test('transfer updates both affected parent totals', function () {
    $user = User::factory()->create();
    $sourceParent = SavingsAccount::factory()->create([
        'name' => 'Source parent',
        'amount' => 0,
    ]);
    $targetParent = SavingsAccount::factory()->create([
        'name' => 'Target parent',
        'amount' => 0,
    ]);
    $sourceChild = SavingsAccount::factory()->create([
        'parent_id' => $sourceParent->id,
        'name' => 'Source child',
        'amount' => 80,
    ]);
    $targetChild = SavingsAccount::factory()->create([
        'parent_id' => $targetParent->id,
        'name' => 'Target child',
        'amount' => 20,
    ]);

    $sourceParent->refresh()->syncAmountFromChildren();
    $targetParent->refresh()->syncAmountFromChildren();

    $this->actingAs($user)
        ->post(route('savings.balance-adjustments.store', $targetChild), [
            'operation' => 'add',
            'amount' => 15,
            'related_account_id' => $sourceChild->id,
        ])
        ->assertRedirect();

    expect($sourceChild->fresh()->amount)->toBe('65.00')
        ->and($targetChild->fresh()->amount)->toBe('35.00')
        ->and($sourceParent->fresh()->amount)->toBe('65.00')
        ->and($targetParent->fresh()->amount)->toBe('35.00');
});

test('rejects overdraft on direct subtract and transfer debit side', function () {
    $user = User::factory()->create();
    $account = SavingsAccount::factory()->create([
        'amount' => 10,
    ]);
    $relatedAccount = SavingsAccount::factory()->create([
        'amount' => 5,
    ]);

    $this->actingAs($user)
        ->post(route('savings.balance-adjustments.store', $account), [
            'operation' => 'subtract',
            'amount' => 10.01,
        ])
        ->assertSessionHasErrors('amount');

    expect($account->fresh()->amount)->toBe('10.00');

    $this->actingAs($user)
        ->post(route('savings.balance-adjustments.store', $account), [
            'operation' => 'add',
            'amount' => 5.01,
            'related_account_id' => $relatedAccount->id,
        ])
        ->assertSessionHasErrors('amount');

    expect($account->fresh()->amount)->toBe('10.00')
        ->and($relatedAccount->fresh()->amount)->toBe('5.00');
});

test('rejects parent accounts as target or related account', function () {
    $user = User::factory()->create();
    $parent = SavingsAccount::factory()->create([
        'amount' => 0,
    ]);
    $child = SavingsAccount::factory()->create([
        'parent_id' => $parent->id,
        'amount' => 10,
    ]);
    $otherParent = SavingsAccount::factory()->create([
        'amount' => 0,
    ]);
    $otherChild = SavingsAccount::factory()->create([
        'parent_id' => $otherParent->id,
        'amount' => 10,
    ]);

    $parent->refresh()->syncAmountFromChildren();
    $otherParent->refresh()->syncAmountFromChildren();

    $this->actingAs($user)
        ->post(route('savings.balance-adjustments.store', $parent), [
            'operation' => 'add',
            'amount' => 5,
        ])
        ->assertSessionHasErrors('amount');

    $this->actingAs($user)
        ->post(route('savings.balance-adjustments.store', $child), [
            'operation' => 'add',
            'amount' => 5,
            'related_account_id' => $otherParent->id,
        ])
        ->assertSessionHasErrors('related_account_id');

    expect($child->fresh()->amount)->toBe('10.00')
        ->and($parent->fresh()->amount)->toBe('10.00')
        ->and($otherChild->fresh()->amount)->toBe('10.00')
        ->and($otherParent->fresh()->amount)->toBe('10.00');
});

test('rejects selecting the same account as both target and related account', function () {
    $user = User::factory()->create();
    $account = SavingsAccount::factory()->create([
        'amount' => 100,
    ]);

    $this->actingAs($user)
        ->post(route('savings.balance-adjustments.store', $account), [
            'operation' => 'add',
            'amount' => 5,
            'related_account_id' => $account->id,
        ])
        ->assertSessionHasErrors('related_account_id');

    expect($account->fresh()->amount)->toBe('100.00');
});
