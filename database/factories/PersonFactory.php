<?php

namespace Database\Factories;

use App\Models\Person;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Person>
 */
class PersonFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        $name = fake()->firstName();

        return [
            'slug' => fake()->unique()->slug(2),
            'name' => $name,
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}
