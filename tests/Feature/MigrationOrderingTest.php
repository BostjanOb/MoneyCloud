<?php

test('tax settings migration includes json brackets and removes separate brackets table', function () {
    $migrationFiles = collect(glob(database_path('migrations/*.php')))
        ->map(fn (string $path): string => basename($path))
        ->sort()
        ->values();

    $taxSettingsMigrationPath = database_path('migrations/2026_04_07_191053_create_tax_settings_table.php');
    $taxSettingsMigrationContents = file_get_contents($taxSettingsMigrationPath);

    expect($migrationFiles->contains('2026_04_07_191053_create_tax_settings_table.php'))->toBeTrue()
        ->and($migrationFiles->contains('2026_04_07_191054_create_tax_brackets_table.php'))->toBeFalse()
        ->and($taxSettingsMigrationContents)->toContain("\$table->json('brackets');");
});

test('investment migrations create tables before dependent foreign keys', function () {
    $migrationFiles = collect(glob(database_path('migrations/*.php')))
        ->map(fn (string $path): string => basename($path))
        ->sort()
        ->values();

    $providerIndex = $migrationFiles->search('2026_04_09_065254_create_investment_providers_table.php');
    $symbolIndex = $migrationFiles->search('2026_04_09_065255_create_investment_symbols_table.php');
    $purchaseIndex = $migrationFiles->search('2026_04_09_065256_create_investment_purchases_table.php');
    $seedIndex = $migrationFiles->search('2026_04_09_065257_seed_default_investment_providers.php');
    $capabilitiesIndex = $migrationFiles->search('2026_04_10_105431_add_capabilities_to_investment_providers_table.php');
    $seedMigrationContents = file_get_contents(database_path('migrations/2026_04_09_065257_seed_default_investment_providers.php'));

    expect($providerIndex)->toBeLessThan($symbolIndex);
    expect($symbolIndex)->toBeLessThan($purchaseIndex);
    expect($purchaseIndex)->toBeLessThan($seedIndex);
    expect($seedIndex)->toBeLessThan($capabilitiesIndex)
        ->and($seedMigrationContents)->not->toContain('InvestmentProviderSlug');
});

test('people migration runs before person references', function () {
    $migrationFiles = collect(glob(database_path('migrations/*.php')))
        ->map(fn (string $path): string => basename($path))
        ->sort()
        ->values();

    $paycheckYearsIndex = $migrationFiles->search('2026_04_07_191047_create_paycheck_years_table.php');
    $savingsAccountsIndex = $migrationFiles->search('2026_04_08_195049_create_savings_accounts_table.php');
    $peopleIndex = $migrationFiles->search('2026_04_06_071745_create_people_table.php');
    $peopleMigrationContents = file_get_contents(database_path('migrations/2026_04_06_071745_create_people_table.php'));

    expect($peopleIndex)->toBeLessThan($paycheckYearsIndex)
        ->and($peopleIndex)->toBeLessThan($savingsAccountsIndex)
        ->and($peopleMigrationContents)->toContain("Schema::create('people'")
        ->and($peopleMigrationContents)->toContain("\$table->string('slug')->unique();");
});
