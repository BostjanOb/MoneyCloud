<?php

namespace Database\Seeders;

use App\Models\TaxSetting;
use Illuminate\Database\Seeder;

class TaxSettingSeeder extends Seeder
{
    public function run(): void
    {
        TaxSetting::create([
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
            'child_relief1' => 2995.83,
            'child_relief2' => 3256.77,
            'child_relief3' => 5432.02,
            'brackets' => [
                ['bracket_from' => 0, 'bracket_to' => 9721.43, 'base_tax' => 0, 'rate' => 16],
                ['bracket_from' => 9721.43, 'bracket_to' => 28592.44, 'base_tax' => 1555.43, 'rate' => 26],
                ['bracket_from' => 28592.44, 'bracket_to' => 57184.88, 'base_tax' => 6461.89, 'rate' => 33],
                ['bracket_from' => 57184.88, 'bracket_to' => 82346.23, 'base_tax' => 15897.40, 'rate' => 39],
                ['bracket_from' => 82346.23, 'bracket_to' => null, 'base_tax' => 25710.33, 'rate' => 50],
            ],
        ]);

        TaxSetting::create([
            'year_from' => 2025,
            'year_to' => 2026,
            'general_relief_brackets' => [
                [
                    'income_from' => 0,
                    'income_to' => 16832,
                    'base_relief' => 5260,
                    'formula_constant' => 19736.99,
                    'formula_multiplier' => 1.17259,
                ],
                [
                    'income_from' => 16832,
                    'income_to' => null,
                    'base_relief' => 5260,
                    'formula_constant' => null,
                    'formula_multiplier' => null,
                ],
            ],
            'child_relief1' => 2838.3,
            'child_relief2' => 3085.52,
            'child_relief3' => 5146.39,
            'brackets' => [
                ['bracket_from' => 0, 'bracket_to' => 9210.26, 'base_tax' => 0, 'rate' => 16],
                ['bracket_from' => 9210.26, 'bracket_to' => 27089, 'base_tax' => 1473.64, 'rate' => 26],
                ['bracket_from' => 27089, 'bracket_to' => 54178, 'base_tax' => 6122.11, 'rate' => 33],
                ['bracket_from' => 54178, 'bracket_to' => 78016.32, 'base_tax' => 15061.48, 'rate' => 39],
                ['bracket_from' => 78016.32, 'bracket_to' => null, 'base_tax' => 24358.43, 'rate' => 50],
            ],
        ]);

        TaxSetting::create([
            'year_from' => 2023,
            'year_to' => 2025,
            'general_relief_brackets' => [
                [
                    'income_from' => 0,
                    'income_to' => 16000,
                    'base_relief' => 5000,
                    'formula_constant' => 18761.40,
                    'formula_multiplier' => 1.17259,
                ],
                [
                    'income_from' => 16000,
                    'income_to' => null,
                    'base_relief' => 5000,
                    'formula_constant' => null,
                    'formula_multiplier' => null,
                ],
            ],
            'child_relief1' => 2698,
            'child_relief2' => 2933,
            'child_relief3' => 4892,
            'brackets' => [
                ['bracket_from' => 0, 'bracket_to' => 8755, 'base_tax' => 0, 'rate' => 16],
                ['bracket_from' => 8755, 'bracket_to' => 25750, 'base_tax' => 1400.8, 'rate' => 26],
                ['bracket_from' => 25750, 'bracket_to' => 51500, 'base_tax' => 5819.5, 'rate' => 33],
                ['bracket_from' => 51500, 'bracket_to' => 74160, 'base_tax' => 14317, 'rate' => 39],
                ['bracket_from' => 74160, 'bracket_to' => null, 'base_tax' => 23154, 'rate' => 50],
            ],
        ]);

        TaxSetting::create([
            'year_from' => 2022,
            'year_to' => 2023,
            'general_relief_brackets' => [
                [
                    'income_from' => 0,
                    'income_to' => 13716.33,
                    'base_relief' => 4500,
                    'formula_constant' => 19261.43,
                    'formula_multiplier' => 1.40427,
                ],
                [
                    'income_from' => 13716.33,
                    'income_to' => null,
                    'base_relief' => 4500,
                    'formula_constant' => null,
                    'formula_multiplier' => null,
                ],
            ],
            'child_relief1' => 2510.03,
            'child_relief2' => 2728.72,
            'child_relief3' => 4551.1,
            'brackets' => [
                ['bracket_from' => 0, 'bracket_to' => 8755, 'base_tax' => 0, 'rate' => 16],
                ['bracket_from' => 8755, 'bracket_to' => 25750, 'base_tax' => 1400.8, 'rate' => 26],
                ['bracket_from' => 25750, 'bracket_to' => 51500, 'base_tax' => 5819.5, 'rate' => 33],
                ['bracket_from' => 51500, 'bracket_to' => 74160, 'base_tax' => 14317, 'rate' => 39],
                ['bracket_from' => 74160, 'bracket_to' => null, 'base_tax' => 23154, 'rate' => 45],
            ],
        ]);

        TaxSetting::create([
            'year_from' => 2020,
            'year_to' => 2022,
            'general_relief_brackets' => [
                [
                    'income_from' => 0,
                    'income_to' => 13316.83,
                    'base_relief' => 3500,
                    'formula_constant' => 18700.38,
                    'formula_multiplier' => 1.40427,
                ],
                [
                    'income_from' => 13316.83,
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

        TaxSetting::create([
            'year_from' => 2017,
            'year_to' => 2020,
            'general_relief_brackets' => [
                [
                    'income_from' => 0,
                    'income_to' => 11166.37,
                    'base_relief' => 6519.82,
                    'formula_constant' => null,
                    'formula_multiplier' => null,
                ],
                [
                    'income_from' => 11166.37,
                    'income_to' => 13316.83,
                    'base_relief' => 3302.7,
                    'formula_constant' => 19922.15,
                    'formula_multiplier' => 1.49601,
                ],
                [
                    'income_from' => 13316.83,
                    'income_to' => null,
                    'base_relief' => 3302.7,
                    'formula_constant' => null,
                    'formula_multiplier' => null,
                ],
            ],
            'child_relief1' => 2436.92,
            'child_relief2' => 2649.24,
            'child_relief3' => 4418.54,
            'brackets' => [
                ['bracket_from' => 0, 'bracket_to' => 8021.34, 'base_tax' => 0, 'rate' => 16],
                ['bracket_from' => 8021.34, 'bracket_to' => 20400, 'base_tax' => 1283.41, 'rate' => 27],
                ['bracket_from' => 20400, 'bracket_to' => 48000, 'base_tax' => 4625.65, 'rate' => 34],
                ['bracket_from' => 48000, 'bracket_to' => 70907.2, 'base_tax' => 14009.65, 'rate' => 39],
                ['bracket_from' => 70907.2, 'bracket_to' => null, 'base_tax' => 22943.46, 'rate' => 50],
            ],
        ]);

        TaxSetting::create([
            'year_from' => 2016,
            'year_to' => 2017,
            'general_relief_brackets' => [
                [
                    'income_from' => 0,
                    'income_to' => 10866.37,
                    'base_relief' => 6519.82,
                    'formula_constant' => null,
                    'formula_multiplier' => null,
                ],
                [
                    'income_from' => 10866.37,
                    'income_to' => 12570.89,
                    'base_relief' => 4418.64,
                    'formula_constant' => null,
                    'formula_multiplier' => null,
                ],
                [
                    'income_from' => 12570.89,
                    'income_to' => null,
                    'base_relief' => 3302.7,
                    'formula_constant' => null,
                    'formula_multiplier' => null,
                ],
            ],
            'child_relief1' => 2436.92,
            'child_relief2' => 2649.24,
            'child_relief3' => 4418.54,
            'brackets' => [
                ['bracket_from' => 0, 'bracket_to' => 8021.34, 'base_tax' => 0, 'rate' => 16],
                ['bracket_from' => 8021.34, 'bracket_to' => 20400, 'base_tax' => 1283.41, 'rate' => 27],
                ['bracket_from' => 20400, 'bracket_to' => 70907.2, 'base_tax' => 4625.65, 'rate' => 41],
                ['bracket_from' => 70907.2, 'bracket_to' => null, 'base_tax' => 25333.6, 'rate' => 50],
            ],
        ]);

        TaxSetting::create([
            'year_from' => 2013,
            'year_to' => 2016,
            'general_relief_brackets' => [
                [
                    'income_from' => 0,
                    'income_to' => 10866.37,
                    'base_relief' => 6519.82,
                    'formula_constant' => null,
                    'formula_multiplier' => null,
                ],
                [
                    'income_from' => 10866.37,
                    'income_to' => 12570.89,
                    'base_relief' => 4418.64,
                    'formula_constant' => null,
                    'formula_multiplier' => null,
                ],
                [
                    'income_from' => 12570.89,
                    'income_to' => null,
                    'base_relief' => 3302.7,
                    'formula_constant' => null,
                    'formula_multiplier' => null,
                ],
            ],
            'child_relief1' => 2436.92,
            'child_relief2' => 2649.24,
            'child_relief3' => 4418.54,
            'brackets' => [
                ['bracket_from' => 0, 'bracket_to' => 8021.34, 'base_tax' => 0, 'rate' => 16],
                ['bracket_from' => 8021.34, 'bracket_to' => 18960.28, 'base_tax' => 1283.41, 'rate' => 27],
                ['bracket_from' => 18960.28, 'bracket_to' => 70907.2, 'base_tax' => 4236.92, 'rate' => 41],
                ['bracket_from' => 70907.2, 'bracket_to' => null, 'base_tax' => 25535.16, 'rate' => 50],
            ],
        ]);

        TaxSetting::create([
            'year_from' => 2012,
            'year_to' => 2013,
            'general_relief_brackets' => [
                [
                    'income_from' => 0,
                    'income_to' => 10622.06,
                    'base_relief' => 6373.24,
                    'formula_constant' => null,
                    'formula_multiplier' => null,
                ],
                [
                    'income_from' => 10622.06,
                    'income_to' => 12288.26,
                    'base_relief' => 4319.3,
                    'formula_constant' => null,
                    'formula_multiplier' => null,
                ],
                [
                    'income_from' => 12288.26,
                    'income_to' => null,
                    'base_relief' => 3228.45,
                    'formula_constant' => null,
                    'formula_multiplier' => null,
                ],
            ],
            'child_relief1' => 2382.13,
            'child_relief2' => 2589.68,
            'child_relief3' => 4319.2,
            'brackets' => [
                ['bracket_from' => 0, 'bracket_to' => 7840.53, 'base_tax' => 0, 'rate' => 16],
                ['bracket_from' => 7840.53, 'bracket_to' => 15681.03, 'base_tax' => 1254.48, 'rate' => 27],
                ['bracket_from' => 15681.03, 'bracket_to' => null, 'base_tax' => 3371.42, 'rate' => 41],
            ],
        ]);

        TaxSetting::create([
            'year_from' => 2011,
            'year_to' => 2012,
            'general_relief_brackets' => [
                [
                    'income_from' => 0,
                    'income_to' => 10342.8,
                    'base_relief' => 6205.68,
                    'formula_constant' => null,
                    'formula_multiplier' => null,
                ],
                [
                    'income_from' => 10342.8,
                    'income_to' => 11965.2,
                    'base_relief' => 4205.74,
                    'formula_constant' => null,
                    'formula_multiplier' => null,
                ],
                [
                    'income_from' => 11965.2,
                    'income_to' => null,
                    'base_relief' => 3143.57,
                    'formula_constant' => null,
                    'formula_multiplier' => null,
                ],
            ],
            'child_relief1' => 2319.5,
            'child_relief2' => 2521.59,
            'child_relief3' => 4205.64,
            'brackets' => [
                ['bracket_from' => 0, 'bracket_to' => 7634.4, 'base_tax' => 0, 'rate' => 16],
                ['bracket_from' => 7634.4, 'bracket_to' => 15268.77, 'base_tax' => 1221.5, 'rate' => 27],
                ['bracket_from' => 15268.77, 'bracket_to' => null, 'base_tax' => 3282.78, 'rate' => 41],
            ],
        ]);
    }
}
