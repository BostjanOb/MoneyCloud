<?php

namespace Database\Seeders;

use App\Enums\InvestmentProviderSlug;
use App\Models\InvestmentProvider;
use Illuminate\Database\Seeder;

class InvestmentProviderSeeder extends Seeder
{
    public function run(): void
    {
        collect([
            [
                'slug' => InvestmentProviderSlug::IBKR,
                'name' => InvestmentProviderSlug::IBKR->label(),
                'sort_order' => 1,
            ],
            [
                'slug' => InvestmentProviderSlug::ILIRIKA,
                'name' => InvestmentProviderSlug::ILIRIKA->label(),
                'sort_order' => 2,
            ],
        ])->each(function (array $provider): void {
            InvestmentProvider::query()->updateOrCreate(
                ['slug' => $provider['slug']],
                [
                    'name' => $provider['name'],
                    'sort_order' => $provider['sort_order'],
                ],
            );
        });
    }
}
