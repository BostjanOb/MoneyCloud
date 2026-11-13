<?php

use App\Models\SavingsAccount;
use App\Models\SavingsTransferTemplate;
use App\Models\SavingsTransferTemplateItem;
use App\Models\User;

test('creating a transfer template requires authentication', function () {
    $this->post(route('savings.transfer-templates.store'), [])
        ->assertRedirect(route('login'));
});

test('transfer template is created with its target accounts', function () {
    $user = User::factory()->create();
    $taja = SavingsAccount::factory()->create();
    $pika = SavingsAccount::factory()->create();

    $this->actingAs($user)
        ->post(route('savings.transfer-templates.store'), [
            'name' => 'Otroški dodatki',
            'items' => [
                ['savings_account_id' => $taja->id, 'amount' => 36],
                ['savings_account_id' => $pika->id, 'amount' => '36.50'],
            ],
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $template = SavingsTransferTemplate::where('name', 'Otroški dodatki')->firstOrFail();

    expect($template->items()->pluck('amount', 'savings_account_id')->all())->toBe([
        $taja->id => '36.00',
        $pika->id => '36.50',
    ]);
});

test('updating a transfer template replaces its target accounts', function () {
    $user = User::factory()->create();
    $template = SavingsTransferTemplate::factory()->create(['name' => 'Staro ime']);
    $removedItem = SavingsTransferTemplateItem::factory()->for($template, 'template')->create();
    $rubi = SavingsAccount::factory()->create();

    $this->actingAs($user)
        ->put(route('savings.transfer-templates.update', $template), [
            'name' => 'Novo ime',
            'items' => [
                ['savings_account_id' => $rubi->id, 'amount' => 40],
            ],
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($template->fresh()->name)->toBe('Novo ime')
        ->and($template->items()->pluck('amount', 'savings_account_id')->all())->toBe([$rubi->id => '40.00']);
    $this->assertModelMissing($removedItem);
});

test('deleting a transfer template removes it and its items', function () {
    $user = User::factory()->create();
    $template = SavingsTransferTemplate::factory()->create();
    $item = SavingsTransferTemplateItem::factory()->for($template, 'template')->create();

    $this->actingAs($user)
        ->delete(route('savings.transfer-templates.destroy', $template))
        ->assertRedirect();

    $this->assertModelMissing($template);
    $this->assertModelMissing($item);
});

test('transfer template requires a name and at least one target account', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('savings.transfer-templates.store'), [])
        ->assertSessionHasErrors([
            'name',
            'items' => 'Dodajte vsaj en ciljni račun.',
        ]);
});

test('transfer template rejects the same target account twice', function () {
    $user = User::factory()->create();
    $account = SavingsAccount::factory()->create();

    $this->actingAs($user)
        ->post(route('savings.transfer-templates.store'), [
            'name' => 'Podvojeno',
            'items' => [
                ['savings_account_id' => $account->id, 'amount' => 10],
                ['savings_account_id' => $account->id, 'amount' => 20],
            ],
        ])
        ->assertSessionHasErrors(['items.0.savings_account_id' => 'Ciljni račun je izbran večkrat.']);
});

test('transfer template rejects a target account that has subaccounts', function () {
    $user = User::factory()->create();
    $parent = SavingsAccount::factory()->create();
    SavingsAccount::factory()->create(['parent_id' => $parent->id]);

    $this->actingAs($user)
        ->post(route('savings.transfer-templates.store'), [
            'name' => 'Nadrejeni',
            'items' => [
                ['savings_account_id' => $parent->id, 'amount' => 10],
            ],
        ])
        ->assertSessionHasErrors(['items.0.savings_account_id' => 'Ciljni račun mora biti leaf račun brez podračunov.']);
});

test('transfer template rejects a non-positive amount', function () {
    $user = User::factory()->create();
    $account = SavingsAccount::factory()->create();

    $this->actingAs($user)
        ->post(route('savings.transfer-templates.store'), [
            'name' => 'Nič',
            'items' => [
                ['savings_account_id' => $account->id, 'amount' => 0],
            ],
        ])
        ->assertSessionHasErrors(['items.0.amount' => 'Znesek mora biti večji od 0.']);
});

test('transfer template can store a default source account', function () {
    $user = User::factory()->create();
    $source = SavingsAccount::factory()->create();
    $taja = SavingsAccount::factory()->create();

    $this->actingAs($user)
        ->post(route('savings.transfer-templates.store'), [
            'name' => 'Otroški dodatki',
            'source_account_id' => $source->id,
            'items' => [
                ['savings_account_id' => $taja->id, 'amount' => 36],
            ],
        ])
        ->assertSessionHasNoErrors();

    expect(SavingsTransferTemplate::where('name', 'Otroški dodatki')->firstOrFail()->source_account_id)
        ->toBe($source->id);
});

test('transfer template rejects a default source that is also a target', function () {
    $user = User::factory()->create();
    $taja = SavingsAccount::factory()->create();

    $this->actingAs($user)
        ->post(route('savings.transfer-templates.store'), [
            'name' => 'Krožno',
            'source_account_id' => $taja->id,
            'items' => [
                ['savings_account_id' => $taja->id, 'amount' => 36],
            ],
        ])
        ->assertSessionHasErrors(['source_account_id' => 'Izvorni račun ne sme biti med ciljnimi računi.']);
});

test('transfer template rejects a default source that has subaccounts', function () {
    $user = User::factory()->create();
    $parent = SavingsAccount::factory()->create();
    SavingsAccount::factory()->create(['parent_id' => $parent->id]);
    $taja = SavingsAccount::factory()->create();

    $this->actingAs($user)
        ->post(route('savings.transfer-templates.store'), [
            'name' => 'Nadrejeni vir',
            'source_account_id' => $parent->id,
            'items' => [
                ['savings_account_id' => $taja->id, 'amount' => 36],
            ],
        ])
        ->assertSessionHasErrors(['source_account_id' => 'Izvorni račun mora biti leaf račun brez podračunov.']);
});
