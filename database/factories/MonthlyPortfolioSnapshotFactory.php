<?php

namespace Database\Factories;

use App\Models\MonthlyPortfolioSnapshot;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MonthlyPortfolioSnapshot>
 */
class MonthlyPortfolioSnapshotFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $savingsAmount = fake()->randomFloat(2, 0, 50000);
        $bondAmount = fake()->randomFloat(2, 0, 20000);
        $etfAmount = fake()->randomFloat(2, 0, 50000);
        $cryptoAmount = fake()->randomFloat(2, 0, 50000);
        $stockAmount = fake()->randomFloat(2, 0, 50000);

        return [
            'month_date' => CarbonImmutable::instance(
                fake()->dateTimeBetween('-2 years', 'now'),
            )->startOfMonth(),
            'savings_amount' => number_format($savingsAmount, 2, '.', ''),
            'bond_amount' => number_format($bondAmount, 2, '.', ''),
            'etf_amount' => number_format($etfAmount, 2, '.', ''),
            'crypto_amount' => number_format($cryptoAmount, 2, '.', ''),
            'stock_amount' => number_format($stockAmount, 2, '.', ''),
            'total_amount' => number_format(
                $savingsAmount + $bondAmount + $etfAmount + $cryptoAmount + $stockAmount,
                2,
                '.',
                '',
            ),
            'source' => fake()->randomElement([
                MonthlyPortfolioSnapshot::SOURCE_MANUAL,
                MonthlyPortfolioSnapshot::SOURCE_SCHEDULED,
            ]),
        ];
    }
}
