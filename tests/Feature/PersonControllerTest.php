<?php

use App\Models\PaycheckYear;
use App\Models\Person;
use App\Models\SavingsAccount;
use App\Models\User;

test('people index requires authentication', function () {
    $this->get(route('people.index'))
        ->assertRedirect(route('login'));
});

test('authenticated user can view people pages', function () {
    $user = User::factory()->create();
    $person = Person::where('slug', 'bostjan')->firstOrFail();

    $this->actingAs($user)
        ->get(route('people.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Osebe')
            ->has('people', 2)
            ->where('people.0.name', 'Boštjan')
        );

    $this->actingAs($user)
        ->get(route('people.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('OsebeForm')
            ->where('person', null)
        );

    $this->actingAs($user)
        ->get(route('people.edit', $person))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('OsebeForm')
            ->where('person.name', 'Boštjan')
            ->where('person.slug', 'bostjan')
        );
});

test('can store update deactivate and reactivate a person', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('people.store'), [
            'name' => 'Maja Novak',
            'slug' => '',
            'is_active' => true,
            'sort_order' => 3,
        ])
        ->assertRedirect(route('people.index'));

    $person = Person::where('slug', 'maja-novak')->firstOrFail();

    expect($person->name)->toBe('Maja Novak')
        ->and($person->is_active)->toBeTrue()
        ->and($person->sort_order)->toBe(3);

    $this->actingAs($user)
        ->put(route('people.update', $person), [
            'name' => 'Maja',
            'slug' => 'maja',
            'is_active' => true,
            'sort_order' => 4,
        ])
        ->assertRedirect(route('people.index'));

    expect($person->fresh()->slug)->toBe('maja')
        ->and($person->fresh()->name)->toBe('Maja')
        ->and($person->fresh()->sort_order)->toBe(4);

    $this->actingAs($user)
        ->delete(route('people.destroy', $person->fresh()))
        ->assertRedirect(route('people.index'));

    expect($person->fresh()->is_active)->toBeFalse();

    $this->actingAs($user)
        ->put(route('people.update', $person->fresh()), [
            'name' => 'Maja',
            'slug' => 'maja',
            'is_active' => true,
            'sort_order' => 4,
        ])
        ->assertRedirect(route('people.index'));

    expect($person->fresh()->is_active)->toBeTrue();
});

test('validates unique person slugs', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('people.store'), [
            'name' => 'Boštjan',
            'slug' => 'bostjan',
            'is_active' => true,
            'sort_order' => 9,
        ])
        ->assertSessionHasErrors('slug');
});

test('deactivating a person preserves finance records', function () {
    $user = User::factory()->create();
    $person = Person::where('slug', 'bostjan')->firstOrFail();

    $paycheckYear = PaycheckYear::factory()->create([
        'person_id' => $person->id,
    ]);
    $savingsAccount = SavingsAccount::factory()->create([
        'person_id' => $person->id,
    ]);

    $this->actingAs($user)
        ->delete(route('people.destroy', $person))
        ->assertRedirect(route('people.index'));

    expect($person->fresh()->is_active)->toBeFalse();
    $this->assertDatabaseHas('paycheck_years', ['id' => $paycheckYear->id]);
    $this->assertDatabaseHas('savings_accounts', ['id' => $savingsAccount->id]);
});
