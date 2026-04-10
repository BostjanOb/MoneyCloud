<?php

namespace Database\Factories;

use App\Models\PaycheckYear;
use App\Models\Person;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaycheckYear>
 */
class PaycheckYearFactory extends Factory
{
    public function definition(): array
    {
        return [
            'person_id' => Person::factory(),
            'year' => fake()->numberBetween(2020, 2026),
            'child1_months' => 12,
            'child2_months' => 12,
            'child3_months' => 0,
        ];
    }
}
