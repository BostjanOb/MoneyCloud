<?php

use App\Enums\Employee;
use App\Models\SavingsAccount;
use App\Models\User;

test('savings index requires authentication', function () {
    $this->get(route('savings.index'))->assertRedirect(route('login'));
});

test('authenticated user can view savings index with totals and hierarchy', function () {
    $user = User::factory()->create();

    $firstAlpha = SavingsAccount::factory()->create([
        'name' => 'Alpha',
        'owner' => Employee::JASNA,
        'amount' => 300,
        'apy' => 3,
        'sort_order' => 1,
    ]);

    $secondAlpha = SavingsAccount::factory()->create([
        'name' => 'Alpha',
        'owner' => Employee::BOSTJAN,
        'amount' => 100,
        'apy' => 1.2,
        'sort_order' => 1,
    ]);

    $parent = SavingsAccount::factory()->create([
        'name' => 'Skupni račun',
        'owner' => Employee::BOSTJAN,
        'amount' => 0,
        'apy' => 2.5,
        'sort_order' => 2,
    ]);

    SavingsAccount::factory()->create([
        'parent_id' => $parent->id,
        'name' => 'Podračun B',
        'owner' => Employee::BOSTJAN,
        'amount' => 200,
        'apy' => 1.5,
        'sort_order' => 2,
    ]);

    SavingsAccount::factory()->create([
        'parent_id' => $parent->id,
        'name' => 'Podračun A',
        'owner' => Employee::JASNA,
        'amount' => 50,
        'apy' => 1.1,
        'sort_order' => 1,
    ]);

    $parent->refresh()->syncAmountFromChildren();

    $this->actingAs($user)
        ->get(route('savings.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Varcevanje/Index')
            ->has('accounts', 3)
            ->where('accounts.0.id', $firstAlpha->id)
            ->where('accounts.0.name', 'Alpha')
            ->where('accounts.0.sort_order', 1)
            ->where('accounts.1.id', $secondAlpha->id)
            ->where('accounts.1.name', 'Alpha')
            ->where('accounts.1.sort_order', 1)
            ->where('accounts.2.name', 'Skupni račun')
            ->where('accounts.2.amount', '250.00')
            ->where('accounts.2.sort_order', 2)
            ->where('accounts.2.children.0.name', 'Podračun A')
            ->where('accounts.2.children.0.sort_order', 1)
            ->where('accounts.2.children.0.amount', '50.00')
            ->where('accounts.2.children.1.name', 'Podračun B')
            ->where('accounts.2.children.1.sort_order', 2)
            ->where('totals.amount', '650.00')
            ->where('ownerOptions.0.label', 'Boštjan')
        );
});

test('can store a root savings account', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('savings.store'), [
            'name' => 'GBKR',
            'owner' => Employee::BOSTJAN->value,
            'amount' => 1200,
            'apy' => 2.5,
            'sort_order' => 7,
            'parent_id' => null,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('savings_accounts', [
        'name' => 'GBKR',
        'owner' => Employee::BOSTJAN->value,
        'amount' => 1200,
        'apy' => 2.5,
        'sort_order' => 7,
        'parent_id' => null,
    ]);
});

test('creating the first subaccount converts parent amount to sum of children', function () {
    $user = User::factory()->create();
    $parent = SavingsAccount::factory()->create([
        'amount' => 999.99,
    ]);

    $this->actingAs($user)
        ->post(route('savings.store'), [
            'name' => 'Podračun',
            'owner' => Employee::JASNA->value,
            'amount' => 250,
            'apy' => 1.2,
            'sort_order' => 3,
            'parent_id' => $parent->id,
        ])
        ->assertRedirect();

    expect($parent->fresh()->amount)->toBe('250.00');
});

test('cannot create a subaccount under another subaccount', function () {
    $user = User::factory()->create();
    $parent = SavingsAccount::factory()->create();
    $child = SavingsAccount::factory()->create([
        'parent_id' => $parent->id,
    ]);

    $parent->refresh()->syncAmountFromChildren();

    $this->actingAs($user)
        ->post(route('savings.store'), [
            'name' => 'Neveljavni podračun',
            'owner' => Employee::BOSTJAN->value,
            'amount' => 10,
            'apy' => 0,
            'sort_order' => 0,
            'parent_id' => $child->id,
        ])
        ->assertSessionHasErrors('parent_id');
});

test('cannot set savings account as its own parent', function () {
    $user = User::factory()->create();
    $account = SavingsAccount::factory()->create();

    $this->actingAs($user)
        ->put(route('savings.update', $account), [
            'name' => $account->name,
            'owner' => $account->owner->value,
            'amount' => $account->amount,
            'apy' => $account->apy,
            'sort_order' => $account->sort_order,
            'parent_id' => $account->id,
        ])
        ->assertSessionHasErrors('parent_id');
});

test('updating a subaccount updates its parent amount', function () {
    $user = User::factory()->create();
    $parent = SavingsAccount::factory()->create([
        'amount' => 0,
    ]);
    $child = SavingsAccount::factory()->create([
        'parent_id' => $parent->id,
        'amount' => 100,
        'apy' => 1,
        'sort_order' => 1,
    ]);

    $parent->refresh()->syncAmountFromChildren();

    $this->actingAs($user)
        ->put(route('savings.update', $child), [
            'name' => 'Posodobljen podračun',
            'owner' => Employee::JASNA->value,
            'amount' => 180,
            'apy' => 2.5,
            'sort_order' => 4,
            'parent_id' => $parent->id,
        ])
        ->assertRedirect();

    expect($child->fresh()->amount)->toBe('180.00')
        ->and($child->fresh()->sort_order)->toBe(4)
        ->and($parent->fresh()->amount)->toBe('180.00');
});

test('parent account with children allows meta updates but rejects manual amount changes', function () {
    $user = User::factory()->create();
    $parent = SavingsAccount::factory()->create([
        'name' => 'Staro ime',
        'owner' => Employee::BOSTJAN,
        'apy' => 1,
    ]);

    SavingsAccount::factory()->create([
        'parent_id' => $parent->id,
        'amount' => 400,
    ]);

    $parent->refresh()->syncAmountFromChildren();

    $this->actingAs($user)
        ->put(route('savings.update', $parent), [
            'name' => 'Novo ime',
            'owner' => Employee::JASNA->value,
            'apy' => 3.5,
            'sort_order' => 9,
        ])
        ->assertRedirect();

    expect($parent->fresh()->name)->toBe('Novo ime')
        ->and($parent->fresh()->owner)->toBe(Employee::JASNA)
        ->and($parent->fresh()->apy)->toBe('3.50')
        ->and($parent->fresh()->sort_order)->toBe(9)
        ->and($parent->fresh()->amount)->toBe('400.00');

    $this->actingAs($user)
        ->put(route('savings.update', $parent), [
            'name' => 'Še eno ime',
            'owner' => Employee::JASNA->value,
            'apy' => 4,
            'sort_order' => 9,
            'amount' => 999,
        ])
        ->assertSessionHasErrors('amount');
});

test('deleting a subaccount updates parent amount', function () {
    $user = User::factory()->create();
    $parent = SavingsAccount::factory()->create(['amount' => 0]);
    $firstChild = SavingsAccount::factory()->create([
        'parent_id' => $parent->id,
        'amount' => 100,
    ]);
    $secondChild = SavingsAccount::factory()->create([
        'parent_id' => $parent->id,
        'amount' => 50,
    ]);

    $parent->refresh()->syncAmountFromChildren();

    $this->actingAs($user)
        ->delete(route('savings.destroy', $firstChild))
        ->assertRedirect();

    expect($parent->fresh()->amount)->toBe('50.00');
    $this->assertDatabaseMissing('savings_accounts', ['id' => $firstChild->id]);

    $this->actingAs($user)
        ->delete(route('savings.destroy', $secondChild))
        ->assertRedirect();

    expect($parent->fresh()->amount)->toBe('0.00');
});

test('deleting a parent account cascades to its subaccounts', function () {
    $user = User::factory()->create();
    $parent = SavingsAccount::factory()->create();
    $child = SavingsAccount::factory()->create([
        'parent_id' => $parent->id,
    ]);

    $this->actingAs($user)
        ->delete(route('savings.destroy', $parent))
        ->assertRedirect();

    $this->assertDatabaseMissing('savings_accounts', ['id' => $parent->id]);
    $this->assertDatabaseMissing('savings_accounts', ['id' => $child->id]);
});
