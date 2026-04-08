<?php

use App\Models\TaxSetting;
use App\Models\User;

test('nastavitve page requires authentication', function () {
    $this->get(route('place.nastavitve'))->assertRedirect(route('login'));
});

test('create tax setting page requires authentication', function () {
    $this->get(route('place.nastavitve.create'))->assertRedirect(route('login'));
});

test('nastavitve page renders for authenticated user', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('place.nastavitve'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Place/Nastavitve')
            ->where('taxSettings', [])
        );
});

test('create tax setting page renders for authenticated user', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('place.nastavitve.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Place/NastavitveForm')
            ->where('taxSetting', null)
        );
});

test('edit tax setting page renders for authenticated user', function () {
    $user = User::factory()->create();
    $setting = TaxSetting::factory()->create();

    $this->actingAs($user)
        ->get(route('place.nastavitve.edit', $setting))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Place/NastavitveForm')
            ->where('taxSetting.id', $setting->id)
        );
});

test('nastavitve page lists newest tax settings first', function () {
    $user = User::factory()->create();
    $olderSetting = TaxSetting::factory()->create([
        'year_from' => 2024,
        'year_to' => 2024,
    ]);
    $newerSetting = TaxSetting::factory()->create([
        'year_from' => 2026,
        'year_to' => null,
    ]);

    $this->actingAs($user)
        ->get(route('place.nastavitve'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Place/Nastavitve')
            ->where('taxSettings.0.id', $newerSetting->id)
            ->where('taxSettings.1.id', $olderSetting->id)
            ->where('taxSettings.0.general_relief_brackets.0.base_relief', 5551.93)
        );
});

test('can store a tax setting with brackets', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('place.nastavitve.store'), [
            'year_from' => 2019,
            'year_to' => null,
            'general_relief_brackets' => [
                [
                    'income_from' => 1000,
                    'income_to' => null,
                    'base_relief' => 3500,
                    'formula_constant' => null,
                    'formula_multiplier' => null,
                ],
                [
                    'income_from' => 0,
                    'income_to' => 1000,
                    'base_relief' => 4000,
                    'formula_constant' => 100,
                    'formula_multiplier' => 0.5,
                ],
            ],
            'child_relief1' => 2436.92,
            'child_relief2' => 2649.24,
            'child_relief3' => 4418.54,
            'brackets' => [
                ['bracket_from' => 0, 'bracket_to' => 9721.43, 'base_tax' => 0, 'rate' => 16],
                ['bracket_from' => 9721.43, 'bracket_to' => null, 'base_tax' => 1555.43, 'rate' => 26],
            ],
        ])
        ->assertRedirect(route('place.nastavitve'));

    $setting = TaxSetting::query()->where('year_from', 2019)->first();

    expect($setting)->not->toBeNull()
        ->and($setting->general_relief_brackets)->toHaveCount(2)
        ->and($setting->general_relief_brackets[0]['income_from'])->toBe(0)
        ->and($setting->general_relief_brackets[1]['income_from'])->toBe(1000)
        ->and($setting->brackets)->toHaveCount(2)
        ->and($setting->brackets[1]['rate'])->toBe(26);
});

test('can update a tax setting and replace brackets', function () {
    $user = User::factory()->create();
    $setting = TaxSetting::factory()->create();

    $this->actingAs($user)
        ->put(route('place.nastavitve.update', $setting), [
            'year_from' => 2027,
            'year_to' => null,
            'general_relief_brackets' => [
                [
                    'income_from' => 2000,
                    'income_to' => null,
                    'base_relief' => 3600,
                    'formula_constant' => null,
                    'formula_multiplier' => null,
                ],
                [
                    'income_from' => 0,
                    'income_to' => 2000,
                    'base_relief' => 3900,
                    'formula_constant' => 250,
                    'formula_multiplier' => 0.8,
                ],
            ],
            'child_relief1' => 2500,
            'child_relief2' => 2700,
            'child_relief3' => 4500,
            'brackets' => [
                ['bracket_from' => 1000, 'bracket_to' => 8000, 'base_tax' => 100, 'rate' => 15],
                ['bracket_from' => 8000, 'bracket_to' => null, 'base_tax' => 1150, 'rate' => 27],
            ],
        ])
        ->assertRedirect(route('place.nastavitve'));

    $updatedSetting = $setting->fresh();

    expect($updatedSetting->year_from)->toBe(2027)
        ->and($updatedSetting->general_relief_brackets)->toHaveCount(2)
        ->and($updatedSetting->general_relief_brackets[0]['income_from'])->toBe(0)
        ->and($updatedSetting->general_relief_brackets[1]['income_from'])->toBe(2000)
        ->and($updatedSetting->brackets)->toHaveCount(2)
        ->and($updatedSetting->brackets[0]['bracket_from'])->toBe(1000)
        ->and($updatedSetting->brackets[1]['rate'])->toBe(27);
});

test('rejects incomplete formula fields for general relief brackets', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('place.nastavitve.store'), [
            'year_from' => 2026,
            'year_to' => null,
            'general_relief_brackets' => [
                [
                    'income_from' => 0,
                    'income_to' => 17766.18,
                    'base_relief' => 5551.93,
                    'formula_constant' => 20832.39,
                    'formula_multiplier' => null,
                ],
            ],
            'child_relief1' => 2436.92,
            'child_relief2' => 2649.24,
            'child_relief3' => 4418.54,
            'brackets' => [
                ['bracket_from' => 0, 'bracket_to' => 9721.43, 'base_tax' => 0, 'rate' => 16],
            ],
        ])
        ->assertSessionHasErrors(['general_relief_brackets.0']);
});

test('can delete a tax setting', function () {
    $user = User::factory()->create();
    $setting = TaxSetting::factory()->create();

    $this->actingAs($user)
        ->delete(route('place.nastavitve.destroy', $setting))
        ->assertRedirect(route('place.nastavitve'));

    $this->assertDatabaseMissing('tax_settings', ['id' => $setting->id]);
});
