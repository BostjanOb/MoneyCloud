<?php

namespace Database\Factories;

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
            'requires_linked_savings_account' => false,
            'supported_symbol_types' => [],
            'sort_order' => 0,
        ];
    }

    public function ibkr(): self
    {
        return $this->state(fn (): array => [
            'slug' => 'ibkr',
            'name' => 'IBKR',
            'requires_linked_savings_account' => true,
            'supported_symbol_types' => ['etf', 'stock', 'crypto'],
            'sort_order' => 1,
        ]);
    }

    public function ilirika(): self
    {
        return $this->state(fn (): array => [
            'slug' => 'ilirika',
            'name' => 'Ilirika',
            'requires_linked_savings_account' => false,
            'supported_symbol_types' => ['bond'],
            'sort_order' => 2,
        ]);
    }

    public function crypto(string $slug = 'nexo', string $name = 'NEXO'): self
    {
        return $this->state(fn (): array => [
            'slug' => $slug,
            'name' => $name,
            'requires_linked_savings_account' => false,
            'supported_symbol_types' => ['crypto'],
            'sort_order' => 10,
        ]);
    }
}
