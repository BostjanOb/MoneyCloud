<?php

namespace Database\Factories;

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
            'type' => 'regres',
            'amount' => fake()->randomFloat(2, 500, 1500),
            'description' => null,
            'paid_at' => null,
        ];
    }
}
