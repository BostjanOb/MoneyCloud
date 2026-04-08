<?php

namespace Database\Factories;

use App\Models\Paycheck;
use App\Models\PaycheckYear;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Paycheck>
 */
class PaycheckFactory extends Factory
{
    public function definition(): array
    {
        return [
            'paycheck_year_id' => PaycheckYear::factory(),
            'month' => fake()->numberBetween(1, 12),
            'net' => fake()->randomFloat(2, 800, 3000),
            'gross' => fake()->randomFloat(2, 1200, 5000),
            'contributions' => fake()->randomFloat(2, 200, 800),
            'taxes' => fake()->randomFloat(2, 100, 600),
        ];
    }
}
