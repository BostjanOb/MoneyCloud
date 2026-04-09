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

    expect($providerIndex)->toBeLessThan($symbolIndex);
    expect($symbolIndex)->toBeLessThan($purchaseIndex);
    expect($purchaseIndex)->toBeLessThan($seedIndex);
});
