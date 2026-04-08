<?php

use App\Enums\BonusType;
use App\Enums\Employee;
use App\Models\Paycheck;
use App\Models\PaycheckYear;
use App\Models\TaxSetting;
use App\Models\User;

function createTaxSettingsForPaycheckControllerTest(): TaxSetting
{
    $setting = TaxSetting::create([
        'year_from' => 2026,
        'year_to' => null,
        'general_relief_brackets' => [
            [
                'income_from' => 0,
                'income_to' => 17766.18,
                'base_relief' => 5551.93,
                'formula_constant' => 20832.39,
                'formula_multiplier' => 1.17259,
            ],
            [
                'income_from' => 17766.18,
                'income_to' => null,
                'base_relief' => 5551.93,
                'formula_constant' => null,
                'formula_multiplier' => null,
            ],
        ],
        'child_relief1' => 2436.92,
        'child_relief2' => 2649.24,
        'child_relief3' => 4418.54,
        'brackets' => [
            ['bracket_from' => 0, 'bracket_to' => 9721.43, 'base_tax' => 0, 'rate' => 16],
            ['bracket_from' => 9721.43, 'bracket_to' => 28592.44, 'base_tax' => 1555.43, 'rate' => 26],
            ['bracket_from' => 28592.44, 'bracket_to' => 57184.88, 'base_tax' => 6461.89, 'rate' => 33],
            ['bracket_from' => 57184.88, 'bracket_to' => 82346.23, 'base_tax' => 15897.40, 'rate' => 39],
            ['bracket_from' => 82346.23, 'bracket_to' => null, 'base_tax' => 25710.33, 'rate' => 50],
        ],
    ]);

    return $setting;
}

test('index page requires authentication', function () {
    $this->get(route('place.index', 'bostjan'))->assertRedirect(route('login'));
});

test('index page renders for authenticated user', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('place.index', 'bostjan'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Place/Index'));
});

test('index page includes all bonus type options', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('place.index', 'bostjan'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Place/Index')
            ->where('bonusTypeOptions', [
                ['value' => BonusType::REGRES->value, 'label' => BonusType::REGRES->label()],
                ['value' => BonusType::SP->value, 'label' => BonusType::SP->label()],
                ['value' => BonusType::BONI_MALICA->value, 'label' => BonusType::BONI_MALICA->label()],
                ['value' => BonusType::OSTALO->value, 'label' => BonusType::OSTALO->label()],
            ])
        );
});

test('index page lists available years newest first', function () {
    $user = User::factory()->create();

    PaycheckYear::factory()->create([
        'employee' => Employee::BOSTJAN,
        'year' => 2024,
    ]);

    PaycheckYear::factory()->create([
        'employee' => Employee::BOSTJAN,
        'year' => 2026,
    ]);

    PaycheckYear::factory()->create([
        'employee' => Employee::BOSTJAN,
        'year' => 2025,
    ]);

    $this->actingAs($user)
        ->get(route('place.index', 'bostjan'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Place/Index')
            ->where('availableYears', [2026, 2025, 2024])
        );
});

test('index page shows paycheck year data', function () {
    $user = User::factory()->create();

    $year = PaycheckYear::factory()->create([
        'employee' => Employee::BOSTJAN,
        'year' => 2026,
    ]);

    Paycheck::factory()->create([
        'paycheck_year_id' => $year->id,
        'month' => 1,
    ]);

    $this->actingAs($user)
        ->get(route('place.index', ['employee' => 'bostjan', 'year' => 2026]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Place/Index')
            ->has('paychecks', 1)
        );
});

test('index page includes paycheck totals for the summary row', function () {
    $user = User::factory()->create();

    $year = PaycheckYear::factory()->create([
        'employee' => Employee::BOSTJAN,
        'year' => 2026,
    ]);

    Paycheck::factory()->create([
        'paycheck_year_id' => $year->id,
        'month' => 1,
        'net' => 1000,
        'gross' => 1500,
        'contributions' => 300,
        'taxes' => 150,
    ]);

    Paycheck::factory()->create([
        'paycheck_year_id' => $year->id,
        'month' => 2,
        'net' => 1100,
        'gross' => 1600,
        'contributions' => 320,
        'taxes' => 160,
    ]);

    $this->actingAs($user)
        ->get(route('place.index', ['employee' => 'bostjan', 'year' => 2026]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Place/Index')
            ->where('calculation.sum_net', '2100.00')
            ->where('calculation.sum_gross', '3100.00')
            ->where('calculation.sum_contributions', '620.00')
            ->where('calculation.sum_taxes', '310.00')
        );
});

test('index page includes projected tax calculation based on available paychecks', function () {
    $user = User::factory()->create();
    createTaxSettingsForPaycheckControllerTest();

    $year = PaycheckYear::factory()->create([
        'employee' => Employee::BOSTJAN,
        'year' => 2026,
    ]);

    Paycheck::factory()->create([
        'paycheck_year_id' => $year->id,
        'month' => 1,
        'net' => 1000,
        'gross' => 1500,
        'contributions' => 300,
        'taxes' => 150,
    ]);

    Paycheck::factory()->create([
        'paycheck_year_id' => $year->id,
        'month' => 2,
        'net' => 1100,
        'gross' => 1600,
        'contributions' => 320,
        'taxes' => 160,
    ]);

    $this->actingAs($user)
        ->get(route('place.index', ['employee' => 'bostjan', 'year' => 2026]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Place/Index')
            ->where('calculation.projection.months_used', 2)
            ->where('calculation.projection.sum_gross', '18600.00')
            ->where('calculation.projection.sum_contributions', '3720.00')
            ->where('calculation.projection.sum_taxes', '1860.00')
        );
});

test('can store a paycheck', function () {
    $user = User::factory()->create();
    $year = PaycheckYear::factory()->create([
        'employee' => Employee::BOSTJAN,
        'year' => 2026,
    ]);

    $this->actingAs($user)
        ->post(route('place.paycheck.store'), [
            'paycheck_year_id' => $year->id,
            'month' => 1,
            'net' => 1000,
            'gross' => 1500,
            'contributions' => 300,
            'taxes' => 150,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('paychecks', [
        'paycheck_year_id' => $year->id,
        'month' => 1,
    ]);
});

test('can store a paycheck without net amount', function () {
    $user = User::factory()->create();
    $year = PaycheckYear::factory()->create([
        'employee' => Employee::BOSTJAN,
        'year' => 2026,
    ]);

    $this->actingAs($user)
        ->post(route('place.paycheck.store'), [
            'paycheck_year_id' => $year->id,
            'month' => 1,
            'gross' => 1500,
            'contributions' => 300,
            'taxes' => 150,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('paychecks', [
        'paycheck_year_id' => $year->id,
        'month' => 1,
        'net' => null,
    ]);
});

test('can update a paycheck', function () {
    $user = User::factory()->create();
    $year = PaycheckYear::factory()->create();
    $paycheck = Paycheck::factory()->create([
        'paycheck_year_id' => $year->id,
        'month' => 1,
        'net' => 1000,
    ]);

    $this->actingAs($user)
        ->put(route('place.paycheck.update', $paycheck), [
            'net' => 1200,
            'gross' => 1800,
            'contributions' => 350,
            'taxes' => 180,
        ])
        ->assertRedirect();

    expect($paycheck->fresh()->net)->toBe('1200.00');
});

test('can update a paycheck without net amount', function () {
    $user = User::factory()->create();
    $year = PaycheckYear::factory()->create();
    $paycheck = Paycheck::factory()->create([
        'paycheck_year_id' => $year->id,
        'month' => 1,
        'net' => 1000,
    ]);

    $this->actingAs($user)
        ->put(route('place.paycheck.update', $paycheck), [
            'net' => '',
            'gross' => 1800,
            'contributions' => 350,
            'taxes' => 180,
        ])
        ->assertRedirect();

    expect($paycheck->fresh()->net)->toBeNull();
});

test('can delete a paycheck', function () {
    $user = User::factory()->create();
    $year = PaycheckYear::factory()->create();
    $paycheck = Paycheck::factory()->create(['paycheck_year_id' => $year->id]);

    $this->actingAs($user)
        ->delete(route('place.paycheck.destroy', $paycheck))
        ->assertRedirect();

    $this->assertDatabaseMissing('paychecks', ['id' => $paycheck->id]);
});

test('validates paycheck store request', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('place.paycheck.store'), [])
        ->assertSessionHasErrors(['paycheck_year_id', 'month', 'gross', 'contributions', 'taxes'])
        ->assertSessionDoesntHaveErrors(['net']);
});
