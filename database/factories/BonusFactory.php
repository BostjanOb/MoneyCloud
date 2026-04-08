<?php

namespace Database\Factories;

use App\Enums\BonusType;
use App\Models\Bonus;
use App\Models\PaycheckYear;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Bonus>
 */
class BonusFactory extends Factory
{
    public function definition(): array
    {
        return [
            'paycheck_year_id' => PaycheckYear::factory(),
            'type' => BonusType::REGRES,
            'amount' => fake()->randomFloat(2, 500, 1500),
            'taxable' => false,
            'paid_tax' => 0,
            'description' => null,
            'paid_at' => null,
        ];
    }
}
