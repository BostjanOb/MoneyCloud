<?php

namespace Database\Factories;

use App\Models\CryptoBalance;
use App\Models\InvestmentProvider;
use App\Models\InvestmentSymbol;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CryptoBalance>
 */
class CryptoBalanceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'investment_provider_id' => InvestmentProvider::factory(),
            'investment_symbol_id' => InvestmentSymbol::factory(),
            'manual_quantity' => fake()->randomFloat(8, 0, 25),
        ];
    }
}
