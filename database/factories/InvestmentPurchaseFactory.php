<?php

namespace Database\Factories;

use App\Enums\InvestmentTransactionType;
use App\Models\InvestmentProvider;
use App\Models\InvestmentPurchase;
use App\Models\InvestmentSymbol;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InvestmentPurchase>
 */
class InvestmentPurchaseFactory extends Factory
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
            'purchased_at' => fake()->dateTimeBetween('-5 years', 'now'),
            'transaction_type' => InvestmentTransactionType::Buy,
            'quantity' => fake()->randomFloat(8, 0.1, 25),
            'price_per_unit' => fake()->randomFloat(2, 1, 1000),
            'fee' => fake()->randomFloat(2, 0, 25),
            'yield' => null,
            'coupon_date' => null,
            'expiry_date' => null,
        ];
    }
}
