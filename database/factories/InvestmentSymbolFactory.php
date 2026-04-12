<?php

namespace Database\Factories;

use App\Enums\InvestmentPriceSource;
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
            'price_source' => InvestmentPriceSource::MANUAL->value,
            'external_source_id' => null,
            'current_price' => fake()->randomFloat(2, 1, 1000),
            'price_synced_at' => null,
        ];
    }

    public function bond(): self
    {
        return $this->state(fn (): array => [
            'type' => InvestmentSymbolType::BOND,
            'price_source' => InvestmentPriceSource::MANUAL->value,
        ]);
    }

    public function crypto(string $symbol = 'BTC'): self
    {
        return $this->state(fn (): array => [
            'type' => InvestmentSymbolType::CRYPTO,
            'symbol' => $symbol,
            'isin' => null,
            'taxable' => false,
            'price_source' => InvestmentPriceSource::MANUAL->value,
            'external_source_id' => null,
        ]);
    }
}
