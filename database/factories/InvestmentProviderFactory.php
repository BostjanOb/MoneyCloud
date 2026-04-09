<?php

namespace Database\Factories;

use App\Enums\InvestmentProviderSlug;
use App\Models\InvestmentProvider;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InvestmentProvider>
 */
class InvestmentProviderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'slug' => fake()->unique()->slug(2),
            'name' => fake()->company(),
            'linked_savings_account_id' => null,
            'sort_order' => 0,
        ];
    }

    public function ibkr(): self
    {
        return $this->state(fn (): array => [
            'slug' => InvestmentProviderSlug::IBKR,
            'name' => InvestmentProviderSlug::IBKR->label(),
            'sort_order' => 1,
        ]);
    }

    public function ilirika(): self
    {
        return $this->state(fn (): array => [
            'slug' => InvestmentProviderSlug::ILIRIKA,
            'name' => InvestmentProviderSlug::ILIRIKA->label(),
            'sort_order' => 2,
        ]);
    }
}
