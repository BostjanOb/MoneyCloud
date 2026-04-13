<?php

namespace Database\Factories;

use App\Models\Person;
use App\Models\SavingsAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SavingsAccount>
 */
class SavingsAccountFactory extends Factory
{
    public function definition(): array
    {
        return [
            'parent_id' => null,
            'person_id' => Person::factory(),
            'name' => fake()->words(2, true),
            'amount' => fake()->randomFloat(2, 0, 50000),
            'apy' => fake()->randomFloat(2, 0, 8),
            'sort_order' => 0,
        ];
    }
}
