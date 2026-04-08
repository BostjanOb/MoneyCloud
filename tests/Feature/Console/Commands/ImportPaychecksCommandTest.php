<?php

use App\Enums\Employee;
use App\Models\Paycheck;
use App\Models\PaycheckYear;
use Illuminate\Console\Command;

function createPaycheckImportCsv(string $contents): string
{
    $path = tempnam(sys_get_temp_dir(), 'paychecks-import-');

    if ($path === false) {
        throw new RuntimeException('Ni bilo mogoče ustvariti začasne CSV datoteke.');
    }

    file_put_contents($path, $contents);

    return $path;
}

test('imports paychecks across years and creates missing paycheck years', function () {
    $path = createPaycheckImportCsv(<<<'CSV'
date,net,gross,contributions,taxes
5. 10. 2010,,"1.809,93  €","399,99  €","236,17  €"
5. 1. 2011,"1.382,25  €","1.803,72  €","398,62  €","238,66  €"
5. 2. 2011,"1.374,84  €","1.809,93  €","399,99  €","239,97  €"
CSV);

    $this->artisan('paychecks:import', ['file' => $path])
        ->expectsChoice('Izberi zaposlenega za uvoz plač', 'Jasna', ['Boštjan', 'Jasna'])
        ->expectsOutputToContain('Izbran zaposleni: Jasna')
        ->expectsOutputToContain('Obdelane vrstice: 3')
        ->expectsOutputToContain('Ustvarjena leta plač: 2')
        ->expectsOutputToContain('Ustvarjene plače: 3')
        ->expectsOutputToContain('Posodobljene plače: 0')
        ->assertExitCode(Command::SUCCESS);

    $year2010 = PaycheckYear::where('employee', Employee::JASNA)
        ->where('year', 2010)
        ->first();
    $year2011 = PaycheckYear::where('employee', Employee::JASNA)
        ->where('year', 2011)
        ->first();

    expect($year2010)->not->toBeNull();
    expect($year2011)->not->toBeNull();
    expect($year2010->child1_months)->toBe(0);
    expect($year2010->child2_months)->toBe(0);
    expect($year2010->child3_months)->toBe(0);

    $this->assertDatabaseHas('paychecks', [
        'paycheck_year_id' => $year2010->id,
        'month' => 10,
        'net' => null,
        'gross' => '1809.93',
        'contributions' => '399.99',
        'taxes' => '236.17',
    ]);

    $this->assertDatabaseHas('paychecks', [
        'paycheck_year_id' => $year2011->id,
        'month' => 1,
        'net' => '1382.25',
        'gross' => '1803.72',
        'contributions' => '398.62',
        'taxes' => '238.66',
    ]);
});

test('updates an existing paycheck when the imported month already exists', function () {
    $paycheckYear = PaycheckYear::factory()->create([
        'employee' => Employee::BOSTJAN,
        'year' => 2011,
        'child1_months' => 0,
        'child2_months' => 0,
        'child3_months' => 0,
    ]);

    $paycheck = Paycheck::factory()->create([
        'paycheck_year_id' => $paycheckYear->id,
        'month' => 1,
        'net' => 1000,
        'gross' => 1500,
        'contributions' => 300,
        'taxes' => 200,
    ]);

    $path = createPaycheckImportCsv(<<<'CSV'
date,net,gross,contributions,taxes
5. 1. 2011,"1.382,25  €","1.803,72  €","398,62  €","238,66  €"
CSV);

    $this->artisan('paychecks:import', ['file' => $path])
        ->expectsChoice('Izberi zaposlenega za uvoz plač', 'Boštjan', ['Boštjan', 'Jasna'])
        ->expectsOutputToContain('Ustvarjena leta plač: 0')
        ->expectsOutputToContain('Ustvarjene plače: 0')
        ->expectsOutputToContain('Posodobljene plače: 1')
        ->assertExitCode(Command::SUCCESS);

    expect($paycheck->fresh()->net)->toBe('1382.25');
    expect($paycheck->fresh()->gross)->toBe('1803.72');
    expect($paycheck->fresh()->contributions)->toBe('398.62');
    expect($paycheck->fresh()->taxes)->toBe('238.66');

    $this->assertDatabaseCount('paychecks', 1);
});

test('fails the whole import when a row contains invalid data', function () {
    $path = createPaycheckImportCsv(<<<'CSV'
date,net,gross,contributions,taxes
5. 10. 2010,,"1.809,93  €","399,99  €","236,17  €"
5. 11. 2010,,"neveljavno","399,99  €","236,17  €"
CSV);

    $this->artisan('paychecks:import', ['file' => $path])
        ->expectsOutputToContain('Vrstica 3: polje bruto znesek vsebuje neveljaven znesek')
        ->assertExitCode(Command::FAILURE);

    $this->assertDatabaseCount('paycheck_years', 0);
    $this->assertDatabaseCount('paychecks', 0);
});

test('fails when the csv file is missing', function () {
    $this->artisan('paychecks:import', ['file' => '/tmp/ne-obstaja-paychecks-import.csv'])
        ->expectsOutputToContain('CSV datoteka [/tmp/ne-obstaja-paychecks-import.csv] ne obstaja.')
        ->assertExitCode(Command::FAILURE);
});

test('fails when the csv header is invalid', function () {
    $path = createPaycheckImportCsv(<<<'CSV'
datum,neto,bruto,prispevki,davki
5. 10. 2010,,"1.809,93  €","399,99  €","236,17  €"
CSV);

    $this->artisan('paychecks:import', ['file' => $path])
        ->expectsOutputToContain('CSV glava mora biti: date,net,gross,contributions,taxes.')
        ->assertExitCode(Command::FAILURE);
});
