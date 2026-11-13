<?php

use App\Models\SavingsAccount;
use App\Models\SavingsTransferTemplate;
use App\Models\SavingsTransferTemplateItem;
use App\Models\User;

/**
 * @param  array<int, SavingsAccount>  $targets
 */
function createTemplateFor(array $targets, string $amount): SavingsTransferTemplate
{
    $template = SavingsTransferTemplate::factory()->create(['name' => 'Otroški dodatki']);

    foreach ($targets as $target) {
        SavingsTransferTemplateItem::factory()->for($template, 'template')->create([
            'savings_account_id' => $target->id,
            'amount' => $amount,
        ]);
    }

    return $template;
}

test('executing a template requires authentication', function () {
    $template = SavingsTransferTemplate::factory()->create();

    $this->post(route('savings.transfer-templates.execute', $template), [])
        ->assertRedirect(route('login'));
});

test('executing a template moves each amount from the source to every target and syncs parents', function () {
    $user = User::factory()->create();
    $source = SavingsAccount::factory()->create(['name' => 'N26', 'amount' => 500]);
    $parent = SavingsAccount::factory()->create(['amount' => 0]);
    $taja = SavingsAccount::factory()->create(['parent_id' => $parent->id, 'amount' => 100]);
    $pika = SavingsAccount::factory()->create(['parent_id' => $parent->id, 'amount' => 200]);
    $rubi = SavingsAccount::factory()->create(['parent_id' => $parent->id, 'amount' => 300]);
    $parent->refresh()->syncAmountFromChildren();
    $template = createTemplateFor([$taja, $pika, $rubi], '36.00');

    $this->actingAs($user)
        ->post(route('savings.transfer-templates.execute', $template), [
            'source_account_id' => $source->id,
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($source->fresh()->amount)->toBe('392.00')
        ->and($taja->fresh()->amount)->toBe('136.00')
        ->and($pika->fresh()->amount)->toBe('236.00')
        ->and($rubi->fresh()->amount)->toBe('336.00')
        ->and($parent->fresh()->amount)->toBe('708.00');
});

test('executing a template from a sibling subaccount keeps the parent total', function () {
    $user = User::factory()->create();
    $parent = SavingsAccount::factory()->create(['amount' => 0]);
    $jasna = SavingsAccount::factory()->create(['parent_id' => $parent->id, 'amount' => 1000]);
    $taja = SavingsAccount::factory()->create(['parent_id' => $parent->id, 'amount' => 0]);
    $parent->refresh()->syncAmountFromChildren();
    $template = createTemplateFor([$taja], '36.00');

    $this->actingAs($user)
        ->post(route('savings.transfer-templates.execute', $template), [
            'source_account_id' => $jasna->id,
        ])
        ->assertRedirect();

    expect($jasna->fresh()->amount)->toBe('964.00')
        ->and($taja->fresh()->amount)->toBe('36.00')
        ->and($parent->fresh()->amount)->toBe('1000.00');
});

test('executing a template fails without changes when the source balance is too low', function () {
    $user = User::factory()->create();
    $source = SavingsAccount::factory()->create(['name' => 'Prazen', 'amount' => 50]);
    $taja = SavingsAccount::factory()->create(['amount' => 0]);
    $pika = SavingsAccount::factory()->create(['amount' => 0]);
    $template = createTemplateFor([$taja, $pika], '36.00');

    $this->actingAs($user)
        ->post(route('savings.transfer-templates.execute', $template), [
            'source_account_id' => $source->id,
        ])
        ->assertSessionHasErrors(['source_account_id' => 'Znesek presega stanje računa Prazen.']);

    expect($source->fresh()->amount)->toBe('50.00')
        ->and($taja->fresh()->amount)->toBe('0.00')
        ->and($pika->fresh()->amount)->toBe('0.00');
});

test('executing a template requires a source account', function () {
    $user = User::factory()->create();
    $template = createTemplateFor([SavingsAccount::factory()->create()], '36.00');

    $this->actingAs($user)
        ->post(route('savings.transfer-templates.execute', $template), [])
        ->assertSessionHasErrors(['source_account_id' => 'Izberite izvorni račun.']);
});

test('executing a template rejects a source that is one of its targets', function () {
    $user = User::factory()->create();
    $taja = SavingsAccount::factory()->create(['amount' => 100]);
    $template = createTemplateFor([$taja], '36.00');

    $this->actingAs($user)
        ->post(route('savings.transfer-templates.execute', $template), [
            'source_account_id' => $taja->id,
        ])
        ->assertSessionHasErrors(['source_account_id' => 'Izvorni račun ne sme biti med ciljnimi računi.']);

    expect($taja->fresh()->amount)->toBe('100.00');
});

test('executing a template rejects a source account that has subaccounts', function () {
    $user = User::factory()->create();
    $parent = SavingsAccount::factory()->create(['amount' => 0]);
    SavingsAccount::factory()->create(['parent_id' => $parent->id, 'amount' => 500]);
    $parent->refresh()->syncAmountFromChildren();
    $template = createTemplateFor([SavingsAccount::factory()->create()], '36.00');

    $this->actingAs($user)
        ->post(route('savings.transfer-templates.execute', $template), [
            'source_account_id' => $parent->id,
        ])
        ->assertSessionHasErrors(['source_account_id' => 'Izvorni račun mora biti leaf račun brez podračunov.']);
});

test('executing a template without target accounts is rejected', function () {
    $user = User::factory()->create();
    $source = SavingsAccount::factory()->create(['amount' => 100]);
    $template = SavingsTransferTemplate::factory()->create();

    $this->actingAs($user)
        ->post(route('savings.transfer-templates.execute', $template), [
            'source_account_id' => $source->id,
        ])
        ->assertSessionHasErrors(['source_account_id' => 'Predloga nima ciljnih računov.']);

    expect($source->fresh()->amount)->toBe('100.00');
});

test('executing a template without a source uses the saved source account', function () {
    $user = User::factory()->create();
    $source = SavingsAccount::factory()->create(['name' => 'N26', 'amount' => 200]);
    $taja = SavingsAccount::factory()->create(['amount' => 0]);
    $pika = SavingsAccount::factory()->create(['amount' => 0]);
    $template = createTemplateFor([$taja, $pika], '36.00');
    $template->update(['source_account_id' => $source->id]);

    $this->actingAs($user)
        ->post(route('savings.transfer-templates.execute', $template))
        ->assertRedirect()
        ->assertSessionHasNoErrors()
        ->assertSessionHas('status', 'Predloga Otroški dodatki izvedena: 72,00 € prenesenih iz računa N26.');

    expect($source->fresh()->amount)->toBe('128.00')
        ->and($taja->fresh()->amount)->toBe('36.00')
        ->and($pika->fresh()->amount)->toBe('36.00');
});

test('executing a template with an explicit source overrides the saved source', function () {
    $user = User::factory()->create();
    $savedSource = SavingsAccount::factory()->create(['amount' => 200]);
    $otherSource = SavingsAccount::factory()->create(['amount' => 200]);
    $taja = SavingsAccount::factory()->create(['amount' => 0]);
    $template = createTemplateFor([$taja], '36.00');
    $template->update(['source_account_id' => $savedSource->id]);

    $this->actingAs($user)
        ->post(route('savings.transfer-templates.execute', $template), [
            'source_account_id' => $otherSource->id,
        ])
        ->assertSessionHasNoErrors();

    expect($savedSource->fresh()->amount)->toBe('200.00')
        ->and($otherSource->fresh()->amount)->toBe('164.00');
});
