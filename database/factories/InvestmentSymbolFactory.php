<?php

namespace Database\Factories;

use App\Enums\InvestmentSymbolType;
use App\Models\InvestmentSymbol;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<InvestmentSymbol>
 */
class InvestmentSymbolFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => fake()->randomElement(InvestmentSymbolType::cases()),
            'symbol' => Str::upper(fake()->unique()->lexify('????')),
            'isin' => fake()->boolean() ? Str::upper(fake()->bothify('??##########')) : null,
            'taxable' => fake()->boolean(70),
            'price_source' => fake()->randomElement(['manual', 'ibkr', 'ilirika']),
            'current_price' => fake()->randomFloat(2, 1, 1000),
        ];
    }

    public function bond(): self
    {
        return $this->state(fn (): array => [
            'type' => InvestmentSymbolType::BOND,
            'price_source' => 'ilirika-manual',
        ]);
    }
}
