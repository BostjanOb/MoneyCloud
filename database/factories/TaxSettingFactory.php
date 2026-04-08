<?php

namespace Database\Factories;

use App\Models\TaxSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TaxSetting>
 */
class TaxSettingFactory extends Factory
{
    public function definition(): array
    {
        return [
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
                ['bracket_from' => 9721.43, 'bracket_to' => null, 'base_tax' => 1555.43, 'rate' => 26],
            ],
        ];
    }
}
