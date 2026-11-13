<?php

namespace Database\Factories;

use App\Models\SavingsAccount;
use App\Models\SavingsTransferTemplate;
use App\Models\SavingsTransferTemplateItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SavingsTransferTemplateItem>
 */
class SavingsTransferTemplateItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'savings_transfer_template_id' => SavingsTransferTemplate::factory(),
            'savings_account_id' => SavingsAccount::factory(),
            'amount' => fake()->randomFloat(2, 1, 100),
        ];
    }
}
