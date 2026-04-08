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
