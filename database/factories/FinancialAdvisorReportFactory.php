<?php

namespace Database\Factories;

use App\Models\FinancialAdvisorReport;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FinancialAdvisorReport>
 */
class FinancialAdvisorReportFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'generated_at' => CarbonImmutable::instance(
                fake()->dateTimeBetween('-3 months', 'now'),
            ),
            'report' => [
                'povzetek' => fake()->sentence(),
                'ocena_neto_premozenja' => fake()->sentence(),
                'mocne_tocke' => [fake()->sentence()],
                'tveganja' => [],
                'priporocila' => [],
                'davcni_nasveti' => [],
                'naslednji_koraki' => [fake()->sentence()],
            ],
        ];
    }
}
