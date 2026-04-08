<?php

use App\Enums\Employee;
use App\Models\Paycheck;
use App\Models\PaycheckYear;
use App\Models\TaxSetting;
use App\Services\TaxCalculationService;

function createTaxSettings2026(): TaxSetting
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

function createTaxSettings2020(): TaxSetting
{
    return TaxSetting::create([
        'year_from' => 2020,
        'year_to' => 2020,
        'general_relief_brackets' => [
            [
                'income_from' => 0,
                'income_to' => null,
                'base_relief' => 3500,
                'formula_constant' => null,
                'formula_multiplier' => null,
            ],
        ],
        'child_relief1' => 2436.92,
        'child_relief2' => 2649.24,
        'child_relief3' => 4418.54,
        'brackets' => [
            ['bracket_from' => 0, 'bracket_to' => 8500, 'base_tax' => 0, 'rate' => 16],
            ['bracket_from' => 8500, 'bracket_to' => 25000, 'base_tax' => 1360, 'rate' => 26],
            ['bracket_from' => 25000, 'bracket_to' => 50000, 'base_tax' => 5650, 'rate' => 33],
            ['bracket_from' => 50000, 'bracket_to' => 72000, 'base_tax' => 13900, 'rate' => 39],
            ['bracket_from' => 72000, 'bracket_to' => null, 'base_tax' => 22480, 'rate' => 50],
        ],
    ]);
}

test('calculates formula-based general relief below the 2026 threshold', function () {
    createTaxSettings2026();

    $year = PaycheckYear::create([
        'employee' => Employee::BOSTJAN,
        'year' => 2026,
        'child1_months' => 0,
        'child2_months' => 0,
        'child3_months' => 0,
    ]);

    // Create a single paycheck: gross 1500, contributions 300, taxes 150
    Paycheck::create([
        'paycheck_year_id' => $year->id,
        'month' => 1,
        'net' => 1000,
        'gross' => 1500,
        'contributions' => 300,
        'taxes' => 150,
    ]);

    $service = new TaxCalculationService;
    $result = $service->calculate($year);

    expect($result['has_tax_settings'])->toBeTrue();
    expect($result['sum_gross'])->toBe('1500.00');
    expect($result['sum_contributions'])->toBe('300.00');

    // OSNOVA = 1500 - 300 = 1200
    expect($result['osnova'])->toBe('1200.00');

    // Splošna olajšava = 5551.93 + (20832.39 - 1.17259 * 1500) = 24625.44
    expect($result['breakdown']['general_relief'])->toBe('24625.44');
    expect($result['olajsave'])->toBe('24625.44');

    // DAVCNA_OSNOVA = max(0, 1200 - 24625.44) = 0
    expect($result['davcna_osnova'])->toBe('0.00');
    expect($result['dohodnina'])->toBe('0.00');
});

test('uses a fixed general relief bracket when no formula is configured', function () {
    createTaxSettings2020();

    $year = PaycheckYear::create([
        'employee' => Employee::BOSTJAN,
        'year' => 2020,
        'child1_months' => 0,
        'child2_months' => 0,
        'child3_months' => 0,
    ]);

    Paycheck::create([
        'paycheck_year_id' => $year->id,
        'month' => 1,
        'net' => 10000,
        'gross' => 14000,
        'contributions' => 0,
        'taxes' => 0,
    ]);

    $service = new TaxCalculationService;
    $result = $service->calculate($year);

    expect($result['breakdown']['general_relief'])->toBe('3500.00');
    expect($result['olajsave'])->toBe('3500.00');
});

test('calculates tax in second bracket with children', function () {
    createTaxSettings2026();

    $year = PaycheckYear::create([
        'employee' => Employee::JASNA,
        'year' => 2026,
        'child1_months' => 12,
        'child2_months' => 12,
        'child3_months' => 0,
    ]);

    // 12 months * gross 1500 = 18000, contributions 300 * 12 = 3600
    for ($m = 1; $m <= 12; $m++) {
        Paycheck::create([
            'paycheck_year_id' => $year->id,
            'month' => $m,
            'net' => 1000,
            'gross' => 1500,
            'contributions' => 300,
            'taxes' => 150,
        ]);
    }

    $service = new TaxCalculationService;
    $result = $service->calculate($year);

    // OSNOVA = 18000 - 3600 = 14400
    expect($result['osnova'])->toBe('14400.00');

    // OLAJSAVE = 5551.93 + 2436.92 + 2649.24 = 10638.09
    expect($result['breakdown']['general_relief'])->toBe('5551.93');
    expect($result['olajsave'])->toBe('10638.09');

    // DAVCNA_OSNOVA = 14400 - 10638.09 = 3761.91
    expect($result['davcna_osnova'])->toBe('3761.91');

    // 3761.91 is in first bracket (0 - 9721.43): 0 + 3761.91 * 0.16 = 601.91
    expect($result['dohodnina'])->toBe('601.91');

    // RAZLIKA = 601.91 - (150 * 12) = 601.91 - 1800 = -1198.09 (vračilo)
    expect($result['razlika'])->toBe('-1198.09');
});

test('uses the fixed 2026 general relief above the threshold', function () {
    createTaxSettings2026();

    $year = PaycheckYear::create([
        'employee' => Employee::BOSTJAN,
        'year' => 2026,
        'child1_months' => 0,
        'child2_months' => 0,
        'child3_months' => 0,
    ]);

    Paycheck::create([
        'paycheck_year_id' => $year->id,
        'month' => 1,
        'net' => 10000,
        'gross' => 20000,
        'contributions' => 3548.07,
        'taxes' => 0,
    ]);

    $service = new TaxCalculationService;
    $result = $service->calculate($year);

    expect($result['breakdown']['general_relief'])->toBe('5551.93');
    expect($result['davcna_osnova'])->toBe('10900.00');

    // 10900 is in bracket 9721.43 - 28592.44
    // dohodnina = 1555.43 + (10900 - 9721.43) * 0.26 = 1555.43 + 306.43 = 1861.86
    expect($result['dohodnina'])->toBe('1861.86');
});

test('returns has_tax_settings false when no settings exist', function () {
    $year = PaycheckYear::create([
        'employee' => Employee::BOSTJAN,
        'year' => 2030,
        'child1_months' => 0,
        'child2_months' => 0,
        'child3_months' => 0,
    ]);

    $service = new TaxCalculationService;
    $result = $service->calculate($year);

    expect($result['has_tax_settings'])->toBeFalse();
});

test('prorates child relief by months', function () {
    createTaxSettings2026();

    $year = PaycheckYear::create([
        'employee' => Employee::BOSTJAN,
        'year' => 2026,
        'child1_months' => 6,
        'child2_months' => 0,
        'child3_months' => 0,
    ]);

    Paycheck::create([
        'paycheck_year_id' => $year->id,
        'month' => 1,
        'net' => 10000,
        'gross' => 20000,
        'contributions' => 0,
        'taxes' => 0,
    ]);

    $service = new TaxCalculationService;
    $result = $service->calculate($year);

    // OLAJSAVE = 5551.93 + (2436.92 / 12 * 6) = 5551.93 + 1218.46 = 6770.39
    expect($result['olajsave'])->toBe('6770.39');
});

test('projects annual tax estimate from the last three paychecks', function () {
    createTaxSettings2026();

    $year = PaycheckYear::create([
        'employee' => Employee::BOSTJAN,
        'year' => 2026,
        'child1_months' => 0,
        'child2_months' => 0,
        'child3_months' => 0,
    ]);

    Paycheck::create([
        'paycheck_year_id' => $year->id,
        'month' => 1,
        'net' => 900,
        'gross' => 1400,
        'contributions' => 200,
        'taxes' => 100,
    ]);

    Paycheck::create([
        'paycheck_year_id' => $year->id,
        'month' => 2,
        'net' => 1200,
        'gross' => 2000,
        'contributions' => 300,
        'taxes' => 120,
    ]);

    Paycheck::create([
        'paycheck_year_id' => $year->id,
        'month' => 3,
        'net' => 1400,
        'gross' => 2200,
        'contributions' => 330,
        'taxes' => 150,
    ]);

    Paycheck::create([
        'paycheck_year_id' => $year->id,
        'month' => 4,
        'net' => 1600,
        'gross' => 2600,
        'contributions' => 390,
        'taxes' => 180,
    ]);

    $service = new TaxCalculationService;
    $result = $service->calculate($year);

    expect($result['projection']['months_used'])->toBe(3);
    expect($result['projection']['sum_gross'])->toBe('27200.00');
    expect($result['projection']['sum_net'])->toBe('16800.00');
    expect($result['projection']['sum_contributions'])->toBe('4080.00');
    expect($result['projection']['sum_taxes'])->toBe('1800.00');
    expect($result['projection']['olajsave'])->toBe('5551.93');
    expect($result['projection']['osnova'])->toBe('23120.00');
    expect($result['projection']['davcna_osnova'])->toBe('17568.07');
    expect($result['projection']['dohodnina'])->toBe('3595.56');
    expect($result['projection']['razlika'])->toBe('1795.56');
});

test('uses the precise yearly calculation when all 12 months are entered', function () {
    createTaxSettings2026();

    $year = PaycheckYear::create([
        'employee' => Employee::JASNA,
        'year' => 2026,
        'child1_months' => 12,
        'child2_months' => 12,
        'child3_months' => 0,
    ]);

    for ($month = 1; $month <= 12; $month++) {
        Paycheck::create([
            'paycheck_year_id' => $year->id,
            'month' => $month,
            'net' => 1000,
            'gross' => 1500,
            'contributions' => 300,
            'taxes' => 150,
        ]);
    }

    $service = new TaxCalculationService;
    $result = $service->calculate($year);

    expect($result['projection']['is_final'])->toBeTrue();
    expect($result['projection']['months_used'])->toBe(12);
    expect($result['projection']['sum_gross'])->toBe($result['sum_gross']);
    expect($result['projection']['sum_net'])->toBe($result['sum_net']);
    expect($result['projection']['sum_contributions'])->toBe($result['sum_contributions']);
    expect($result['projection']['sum_taxes'])->toBe($result['sum_taxes']);
    expect($result['projection']['osnova'])->toBe($result['osnova']);
    expect($result['projection']['olajsave'])->toBe($result['olajsave']);
    expect($result['projection']['davcna_osnova'])->toBe($result['davcna_osnova']);
    expect($result['projection']['dohodnina'])->toBe($result['dohodnina']);
    expect($result['projection']['razlika'])->toBe($result['razlika']);
});
