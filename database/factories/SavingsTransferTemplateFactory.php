<?php

namespace Database\Factories;

use App\Models\SavingsTransferTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SavingsTransferTemplate>
 */
class SavingsTransferTemplateFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true),
            'sort_order' => 0,
        ];
    }
}
